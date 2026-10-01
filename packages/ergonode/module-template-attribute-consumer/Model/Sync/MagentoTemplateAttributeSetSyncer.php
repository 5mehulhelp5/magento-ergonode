<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetResolver;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class MagentoTemplateAttributeSetSyncer
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
        bool $createAttributeSet = false,
        bool $removeObsolete = false
    ): array {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new LocalizedException(__('Ergonode template code is required.'));
        }

        $template = $this->templateCacheProvider->getTemplateStructure($templateCode);
        if ($template === null) {
            throw new LocalizedException(__('Ergonode template "%1" is not imported yet.', $templateCode));
        }
        if (!empty($template['is_deleted'])) {
            throw new LocalizedException(__('Ergonode template "%1" is deleted in the remote source.', $templateCode));
        }

        $stats = $this->emptyStats();
        $attributeSetId = $this->attributeSetResolver->resolveForSync(
            $template,
            null,
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
            $mappedAttributes = $this->collectMappedAttributes($templateCode, $plan, $stats);
            $expectedGroupIds = [];
            $expectedAttributeIds = [];

            if ($mappedAttributes !== []) {
                $claimedAttributeGroupIds = [];
                $groupId = $this->groupSynchronizer->ensure(
                    $templateCode,
                    $attributeSetId,
                    $this->commonSection(),
                    $stats,
                    $claimedAttributeGroupIds
                );

                if ($groupId !== null) {
                    $expectedGroupIds[$groupId] = true;
                    $sortOrder = 10;
                    foreach ($mappedAttributes as $mappedAttribute) {
                        $this->attributeSynchronizer->sync(
                            $attributeSetId,
                            $groupId,
                            $mappedAttribute['attribute_id'],
                            $templateCode,
                            $mappedAttribute['section_code'],
                            $mappedAttribute['ergonode_code'],
                            $mappedAttribute['magento_code'],
                            $sortOrder,
                            $existingRows,
                            $stats
                        );
                        $expectedAttributeIds[$mappedAttribute['attribute_id']] = true;
                        $sortOrder += 10;
                    }
                } else {
                    $stats['attributes_skipped'] += count($mappedAttributes);
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
                __('Unable to sync Ergonode template attributes for "%1": %2', $templateCode, $exception->getMessage())
            );
        }

        $stats['templates_synced'] = 1;

        return $stats;
    }

    /**
     * @param array{
     *     sections: array<int, array{
     *         section: array<string, mixed>,
     *         mapped: array<int, array<string, int|string>>,
     *         skipped: array<int, array<string, int|string|null>>,
     *         duplicates: array<int, array<string, int|string>>
     *     }>
     * } $plan
     * @param array<string, int> $stats
     * @return array<int, array{
     *     section_code: string,
     *     ergonode_code: string,
     *     magento_code: string,
     *     attribute_id: int
     * }>
     */
    private function collectMappedAttributes(string $templateCode, array $plan, array &$stats): array
    {
        $mappedAttributes = [];
        foreach ($plan['sections'] as $plannedSection) {
            $sectionCode = (string)$plannedSection['section']['code'];
            foreach ($plannedSection['skipped'] as $skippedAttribute) {
                $this->addSkippedAttributeReport(
                    $templateCode,
                    $sectionCode,
                    (string)$skippedAttribute['ergonode_code'],
                    isset($skippedAttribute['magento_code'])
                        ? (string)$skippedAttribute['magento_code']
                        : null,
                    (string)$skippedAttribute['reason']
                );
                $stats['attributes_skipped']++;
            }
            foreach ($plannedSection['duplicates'] as $duplicateAttribute) {
                $this->addDuplicateAttributeReport($templateCode, $sectionCode, $duplicateAttribute);
                $stats['attributes_skipped']++;
            }
            foreach ($plannedSection['mapped'] as $mappedAttribute) {
                $mappedAttributes[] = [
                    'section_code' => $sectionCode,
                    'ergonode_code' => (string)$mappedAttribute['ergonode_code'],
                    'magento_code' => (string)$mappedAttribute['magento_code'],
                    'attribute_id' => (int)$mappedAttribute['attribute_id'],
                ];
            }
        }

        return $mappedAttributes;
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
     * @return array{
     *     code: string,
     *     name: string,
     *     is_synthetic: bool,
     *     attribute_group_id: null,
     *     sort_order: int
     * }
     */
    private function commonSection(): array
    {
        return [
            'code' => CommonTemplateGroup::SECTION_CODE,
            'name' => CommonTemplateGroup::GROUP_NAME,
            'is_synthetic' => false,
            'attribute_group_id' => null,
            'sort_order' => 1000,
        ];
    }

    /** @param array<string, int|string> $attribute */
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

    /** @return array<string, int> */
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
}
