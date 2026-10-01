<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetResolver;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class MagentoTemplateStructureSyncer
{
    public function __construct(
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly ChangeReport $changeReport,
        private readonly TemplateStructureResource $resource,
        private readonly TemplateGroupSynchronizer $groupSynchronizer,
        private readonly TemplateAttributeSynchronizer $attributeSynchronizer,
        private readonly TemplateStructureCleaner $structureCleaner,
        private readonly TemplateSyncPlanProvider $syncPlanProvider,
        private readonly TemplateAttributeSetResolver $attributeSetResolver
    ) {
    }

    /**
     * @return array<string, int>
     * @throws LocalizedException
     */
    public function syncTemplate(
        string $templateCode,
        ?int $attributeSetId = null,
        bool $createAttributeSet = false,
        bool $removeObsolete = false
    ): array {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new LocalizedException(__('Ergonode template code is required.'));
        }

        $template = $this->templateCacheProvider->getTemplateStructure($templateCode);
        if (!$template) {
            throw new LocalizedException(__('Ergonode template "%1" is not imported yet.', $templateCode));
        }
        if (!empty($template['is_deleted'])) {
            throw new LocalizedException(__('Ergonode template "%1" is deleted in the remote source.', $templateCode));
        }

        $stats = $this->emptyStats();
        $attributeSetId = $this->attributeSetResolver->resolveForSync(
            $template,
            $attributeSetId,
            $createAttributeSet,
            $stats
        );

        $plan = $this->syncPlanProvider->create($template['sections']);
        $existingRows = $this->resource->loadEntityAttributeRows(
            $attributeSetId,
            array_values($plan['magento_attribute_ids'])
        );

        $this->resource->beginTransaction();
        try {
            $expectedGroupIds = [];
            $expectedAttributeIds = [];
            $claimedAttributeGroupIds = [];

            foreach ($plan['sections'] as $plannedSection) {
                $section = $plannedSection['section'];
                foreach ($plannedSection['skipped'] as $skippedAttribute) {
                    $this->addSkippedAttributeReport(
                        $templateCode,
                        (string)$section['code'],
                        $skippedAttribute['ergonode_code'],
                        $skippedAttribute['magento_code'],
                        $skippedAttribute['reason']
                    );
                    $stats['attributes_skipped']++;
                }

                foreach ($plannedSection['duplicates'] as $duplicateAttribute) {
                    $this->addDuplicateAttributeReport(
                        $templateCode,
                        (string)$section['code'],
                        $duplicateAttribute
                    );
                    $stats['attributes_skipped']++;
                }
                $syncableAttributes = $plannedSection['mapped'];

                if ($syncableAttributes === []) {
                    $this->addSkippedGroupReport($templateCode, (string)$section['code']);
                    $stats['groups_skipped']++;
                    continue;
                }

                $groupId = $this->groupSynchronizer->ensure(
                    $templateCode,
                    $attributeSetId,
                    $section,
                    $stats,
                    $claimedAttributeGroupIds
                );
                if ($groupId === null) {
                    $stats['attributes_skipped'] += count($syncableAttributes);
                    continue;
                }

                $expectedGroupIds[$groupId] = true;
                foreach ($syncableAttributes as $mappedAttribute) {
                    $attributeId = $mappedAttribute['attribute_id'];
                    $this->attributeSynchronizer->sync(
                        $attributeSetId,
                        $groupId,
                        $attributeId,
                        $templateCode,
                        (string)$section['code'],
                        $mappedAttribute['ergonode_code'],
                        $mappedAttribute['magento_code'],
                        $mappedAttribute['sort_order'],
                        $existingRows,
                        $stats
                    );
                    $expectedAttributeIds[$attributeId] = true;
                }
            }

            if ($removeObsolete) {
                $this->structureCleaner->removeObsolete(
                    $templateCode,
                    $attributeSetId,
                    array_keys($expectedGroupIds),
                    array_keys($expectedAttributeIds),
                    $stats
                );
            }

            $this->resource->commit();
        } catch (Throwable $exception) {
            $this->resource->rollBack();
            throw new LocalizedException(
                __('Unable to sync Ergonode template "%1": %2', $templateCode, $exception->getMessage())
            );
        }

        $stats['templates_synced'] = 1;

        return $stats;
    }

    /**
     * @return array<string, int>
     * @throws LocalizedException
     */
    public function syncAssignedTemplates(bool $removeObsolete = false): array
    {
        $summary = $this->emptyStats();

        foreach ($this->templateCacheProvider->getTemplatesWithAttributeSet() as $template) {
            $stats = $this->syncTemplate((string)$template['code'], null, false, $removeObsolete);
            $summary = $this->mergeStats($summary, $stats);
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     * @throws LocalizedException
     */
    public function syncAllTemplates(bool $createMissingAttributeSets = false, bool $removeObsolete = false): array
    {
        $summary = $this->emptyStats();

        foreach ($this->templateCacheProvider->getAllTemplates() as $template) {
            if ((int)($template['attribute_set_id'] ?? 0) <= 0 && !$createMissingAttributeSets) {
                $this->changeReport->add(
                    'template',
                    (string)$template['code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Skipped template because Magento attribute set is not assigned.',
                    []
                );
                $summary['templates_skipped']++;
                continue;
            }

            $stats = $this->syncTemplate(
                (string)$template['code'],
                null,
                $createMissingAttributeSets,
                $removeObsolete
            );
            $summary = $this->mergeStats($summary, $stats);
        }

        return $summary;
    }

    private function addSkippedAttributeReport(
        string $templateCode,
        string $sectionCode,
        string $ergonodeAttributeCode,
        ?string $magentoAttributeCode,
        string $reason
    ): void {
        $message = $reason === MappedTemplateAttributeResolver::SKIP_SYSTEM_ATTRIBUTE
            ? 'Skipped template attribute because Magento manages its placement outside template synchronization.'
            : 'Skipped template attribute because no complete Magento attribute mapping exists.';
        $this->changeReport->add(
            'template_attribute',
            $templateCode . '::' . $sectionCode . '::' . $ergonodeAttributeCode,
            ChangeReport::ACTION_SKIPPED,
            $message,
            [
                'section_code' => $sectionCode,
                'ergonode_attribute_code' => $ergonodeAttributeCode,
                'magento_attribute_code' => (string)($magentoAttributeCode ?? ''),
                'reason' => $reason,
            ]
        );
    }

    /**
     * @param array{ergonode_code: string, magento_code: string, attribute_id: int, sort_order: int} $attribute
     */
    private function addDuplicateAttributeReport(string $templateCode, string $sectionCode, array $attribute): void
    {
        $this->changeReport->add(
            'template_attribute',
            $templateCode . '::' . $sectionCode . '::' . $attribute['ergonode_code'],
            ChangeReport::ACTION_SKIPPED,
            'Skipped duplicate Magento attribute in template structure.',
            [
                'section_code' => $sectionCode,
                'ergonode_attribute_code' => $attribute['ergonode_code'],
                'magento_attribute_code' => $attribute['magento_code'],
                'magento_attribute_id' => $attribute['attribute_id'],
            ]
        );
    }

    private function addSkippedGroupReport(string $templateCode, string $sectionCode): void
    {
        $this->changeReport->add(
            'template_group',
            $templateCode . '::' . $sectionCode,
            ChangeReport::ACTION_SKIPPED,
            'Skipped Magento attribute group because the Ergonode section has no unique mapped Magento attributes.',
            ['section_code' => $sectionCode]
        );
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'templates_synced' => 0,
            'templates_skipped' => 0,
            'attribute_sets_created' => 0,
            'attribute_sets_assigned' => 0,
            'attribute_sets_unchanged' => 0,
            'groups_created' => 0,
            'groups_skipped' => 0,
            'groups_updated' => 0,
            'groups_deleted' => 0,
            'attributes_inserted' => 0,
            'attributes_moved' => 0,
            'attributes_removed' => 0,
            'attributes_unchanged' => 0,
            'attributes_skipped' => 0,
        ];
    }

    /**
     * @param array<string, int> $summary
     * @param array<string, int> $stats
     * @return array<string, int>
     */
    private function mergeStats(array $summary, array $stats): array
    {
        foreach ($summary as $key => $value) {
            $summary[$key] = $value + (int)($stats[$key] ?? 0);
        }

        return $summary;
    }
}
