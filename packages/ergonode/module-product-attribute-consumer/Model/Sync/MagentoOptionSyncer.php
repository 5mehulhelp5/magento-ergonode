<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\ProductAttribute\Model\Sync\OptionMatchKeyResolver;
use Ergonode\ProductAttribute\Model\Sync\OptionPairPlanner;

use Ergonode\ProductAttributeConsumer\Model\Provider\AmbiguousOptionLabelException;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionLabelSyncer;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class MagentoOptionSyncer
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly MagentoOptionProvider $magentoOptionProvider,
        private readonly OptionMatchKeyResolver $matchKeyResolver,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly MagentoOptionLabelSyncer $labelSyncer,
        private readonly OptionLabelResolver $labelResolver,
        private readonly ChangeReport $changeReport,
        private readonly MagentoOptionSyncResource $resource,
        private readonly OptionPairPlanner $optionPairPlanner
    ) {
    }

    /**
     * @param  array<string, mixed> $attributeMapping
     * @return array{
     *     created: int,
     *     linked: int,
     *     mappings_inserted: int,
     *     mappings_updated: int,
     *     labels_updated: int,
     *     sort_order_updated: int,
     *     unchanged: int,
     *     skipped: int,
     *     errors: int
     * }
     * @throws LocalizedException
     */
    public function syncAttributeMapping(array $attributeMapping): array
    {
        $stats = $this->emptyStats();
        $attributeMappingId = (int)($attributeMapping['mapping_id'] ?? 0);
        $ergonodeAttributeCode = (string)($attributeMapping['ergonode_attribute_code'] ?? '');
        $magentoAttributeCode = (string)($attributeMapping['magento_attribute_code'] ?? '');
        if ($attributeMappingId <= 0 || $ergonodeAttributeCode === '' || $magentoAttributeCode === '') {
            throw new LocalizedException(__('Missing option sync attribute mapping context.'));
        }
        $this->labelResolver->requireAdminLanguageCode();

        $rows = $this->resource->loadErgonodeOptions($ergonodeAttributeCode);
        $rows = $this->activeErgonodeOptions($rows, $ergonodeAttributeCode);
        if ($rows === []) {
            return $stats;
        }

        $attribute = $this->attributeRepository->get($magentoAttributeCode);
        $attributeId = (int)$attribute->getAttributeId();
        if ($attributeId <= 0) {
            throw new LocalizedException(__('Magento attribute "%1" does not exist.', $magentoAttributeCode));
        }

        $canCreateOptions = $this->canSyncMagentoAttribute($attribute);
        $magentoOptions = $canCreateOptions
            ? $this->loadTableOptions($attributeId, $attributeMapping)
            : $this->loadNativeOptions($magentoAttributeCode, $attributeMapping);
        $existingMappings = $this->resource->loadExistingMappings($attributeMappingId);
        $existingMappingsByOptionId = $this->keyMappingsByOptionId($existingMappings);
        $plan = $canCreateOptions
            ? $this->planTableMatches($attributeMapping, $rows, $magentoOptions, $existingMappings)
            : ['matched_ids' => [], 'conflicts' => []];
        $processed = [];

        foreach ($rows as $position => $row) {
            $item = $this->processOption(
                $row,
                $position + 1,
                $attributeId,
                $attributeMappingId,
                $ergonodeAttributeCode,
                $magentoAttributeCode,
                $attributeMapping,
                $canCreateOptions,
                $magentoOptions,
                $existingMappings,
                $existingMappingsByOptionId,
                $plan,
                $stats
            );
            if ($item !== null) {
                $processed[] = $item;
            }
        }

        return $this->finalizeLabelsAndReports(
            $processed,
            $attributeId,
            $ergonodeAttributeCode,
            $canCreateOptions,
            $stats
        );
    }

    /**
     * @param  array<string, mixed>                $row
     * @param  array{
     *     by_id: array<int, array<string, mixed>>,
     *     by_match: array<string, array<int, int>>
     * } $magentoOptions
     * @param  array<string, array<string, mixed>> $existingMappings
     * @param  array<int, array<string, mixed>>    $existingMappingsByOptionId
     * @param  array<string, int>                  $stats
     * @return array{
     *     option_code: string,
     *     option_id: int,
     *     sort_order: int,
     *     labels: array<string, string>,
     *     created: bool,
     *     changed: bool,
     *     details: array<string, mixed>
     * }|null
     */
    private function processOption(
        array $row,
        int $position,
        int $attributeId,
        int $attributeMappingId,
        string $ergonodeAttributeCode,
        string $magentoAttributeCode,
        array $attributeMapping,
        bool $canCreateOptions,
        array &$magentoOptions,
        array &$existingMappings,
        array &$existingMappingsByOptionId,
        array $plan,
        array &$stats
    ): ?array {
        $optionCode = (string)$row['option_code'];
        $labels = $this->labelResolver->decode((string)$row['labels_json']);
        $defaultLabel = $this->labelResolver->resolveDefault($labels, $optionCode);
        $sortOrder = (int)($row['sort_order'] ?? 0);
        $sortOrder = $sortOrder > 0 ? $sortOrder : $position;
        $optionId = $this->resolveMagentoOptionId(
            $row,
            $defaultLabel,
            $attributeMapping,
            $magentoOptions,
            $existingMappings,
            $existingMappingsByOptionId,
            $canCreateOptions ? $plan['matched_ids'] : null
        );
        $created = false;
        $changed = false;
        $details = [
            'attribute_mapping_id' => $attributeMappingId,
            'magento_attribute_code' => $magentoAttributeCode,
            'default_label' => $defaultLabel,
        ];

        if ($optionId === null && !$canCreateOptions) {
            $this->reportUnmatchedNativeOption(
                $ergonodeAttributeCode,
                $magentoAttributeCode,
                $optionCode,
                $stats
            );

            return null;
        }

        if ($optionId === null && isset($plan['conflicts'][$optionCode])) {
            $this->changeReport->add(
                'option_sync',
                $ergonodeAttributeCode . '::' . $optionCode,
                ChangeReport::ACTION_SKIPPED,
                $plan['conflicts'][$optionCode],
                ['magento_attribute_code' => $magentoAttributeCode]
            );
            $stats['skipped']++;

            return null;
        }

        if ($optionId === null) {
            try {
                $createdOption = $this->resource->createMagentoOption(
                    $magentoAttributeCode,
                    $defaultLabel,
                    $sortOrder
                );
            } catch (AmbiguousOptionLabelException $exception) {
                $this->changeReport->add(
                    'option_sync',
                    $ergonodeAttributeCode . '::' . $optionCode,
                    ChangeReport::ACTION_SKIPPED,
                    $exception->getMessage(),
                    ['magento_attribute_code' => $magentoAttributeCode]
                );
                $stats['skipped']++;

                return null;
            }
            $optionId = $createdOption['option_id'];
            $magentoOptions['by_id'][$optionId] = [
                'option_id' => $optionId,
                'sort_order' => $sortOrder,
                'label' => '',
            ];
            $created = $createdOption['created'];
            $changed = true;
            if ($created) {
                $stats['created']++;
            }
            $details['created_option_id'] = $optionId;
        }

        $collision = $existingMappingsByOptionId[$optionId] ?? null;
        if ($collision && (string)$collision['ergonode_option_code'] !== $optionCode) {
            $this->reportCollision(
                $ergonodeAttributeCode,
                $optionCode,
                $optionId,
                $collision,
                $details,
                $stats
            );

            return null;
        }

        if ($canCreateOptions && $this->resource->syncSortOrder($optionId, $sortOrder, $magentoOptions)) {
            $stats['sort_order_updated']++;
            $changed = true;
            $details['sort_order_updated'] = $sortOrder;
        }
        $mappingResult = $this->resource->syncMapping(
            $attributeMappingId,
            $optionCode,
            $optionId,
            $sortOrder,
            $existingMappings
        );
        $existingMappingsByOptionId[$optionId] = [
            'ergonode_option_code' => $optionCode,
            'magento_option_id' => $optionId,
        ];
        if ($mappingResult === 'inserted') {
            $stats['linked']++;
            $stats['mappings_inserted']++;
            $changed = true;
            $details['mapping'] = 'inserted';
        } elseif ($mappingResult === 'updated') {
            $stats['linked']++;
            $stats['mappings_updated']++;
            $changed = true;
            $details['mapping'] = 'updated';
        }

        return [
            'option_code' => $optionCode,
            'option_id' => $optionId,
            'sort_order' => $sortOrder,
            'labels' => $labels,
            'created' => $created,
            'changed' => $changed,
            'details' => $details,
        ];
    }

    /**
     * @param array<string, int> $stats
     */
    private function reportUnmatchedNativeOption(
        string $ergonodeAttributeCode,
        string $magentoAttributeCode,
        string $optionCode,
        array &$stats
    ): void {
        $message = 'No matching native Magento option exists; native source options cannot be created.';
        $this->changeReport->add(
            'option_sync',
            $ergonodeAttributeCode . '::' . $optionCode,
            ChangeReport::ACTION_SKIPPED,
            $message,
            ['magento_attribute_code' => $magentoAttributeCode]
        );
        $stats['skipped']++;
    }

    /**
     * @param array<string, mixed> $collision
     * @param array<string, mixed> $details
     * @param array<string, int>   $stats
     */
    private function reportCollision(
        string $ergonodeAttributeCode,
        string $optionCode,
        int $optionId,
        array $collision,
        array $details,
        array &$stats
    ): void {
        $message = sprintf(
            'Magento option ID %d is already mapped to Ergonode option "%s".',
            $optionId,
            (string)$collision['ergonode_option_code']
        );
        $this->changeReport->add(
            'option_sync',
            $ergonodeAttributeCode . '::' . $optionCode,
            ChangeReport::ACTION_ERROR,
            $message,
            $details + ['magento_option_id' => $optionId]
        );
        $stats['errors']++;
    }

    /**
     * @param  array<int, array{
     *     option_code: string,
     *     option_id: int,
     *     sort_order: int,
     *     labels: array<string, string>,
     *     created: bool,
     *     changed: bool,
     *     details: array<string, mixed>
     * }> $processed
     * @param  array<string, int> $stats
     * @return array<string, int>
     */
    private function finalizeLabelsAndReports(
        array $processed,
        int $attributeId,
        string $ergonodeAttributeCode,
        bool $syncLabels,
        array $stats
    ): array {
        $labelsByOptionId = [];
        if ($syncLabels) {
            foreach ($processed as $item) {
                $labelsByOptionId[$item['option_id']] = $item['labels'];
            }
        }
        $labelChanges = $syncLabels
            ? $this->labelSyncer->syncLabelsBatch($attributeId, $labelsByOptionId)
            : [];

        foreach ($processed as $item) {
            $optionId = $item['option_id'];
            $created = $item['created'];
            $changed = $item['changed'];
            $details = $item['details'];
            $labelsUpdated = $labelChanges[$optionId] ?? 0;
            if ($labelsUpdated > 0) {
                $stats['labels_updated'] += $labelsUpdated;
                $changed = true;
                $details['labels_updated'] = $labelsUpdated;
            }
            if (!$created && !$changed) {
                $stats['unchanged']++;
            }

            $this->changeReport->add(
                'option_sync',
                $ergonodeAttributeCode . '::' . $item['option_code'],
                $created
                    ? ChangeReport::ACTION_INSERTED
                    : ($changed ? ChangeReport::ACTION_UPDATED : ChangeReport::ACTION_UNCHANGED),
                $created
                    ? 'Created Magento option and synchronized mapping.'
                    : ($changed ? 'Synchronized Magento option mapping.' : 'Magento option mapping is unchanged.'),
                $details + [
                    'magento_option_id' => $optionId,
                    'sort_order' => $item['sort_order'],
                ]
            );
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed> $attributeMapping
     * @return array{
     *     by_id: array<int, array<string, mixed>>,
     *     by_match: array<string, array<int, int>>
     * }
     */
    private function loadTableOptions(int $attributeId, array $attributeMapping): array
    {
        $options = $this->resource->loadMagentoOptions($attributeId);
        $result = ['by_id' => $options['by_id'], 'by_match' => []];
        $codes = array_map(
            static fn (int $optionId): string => 'option_' . $optionId,
            array_keys($options['by_id'])
        );
        $activeMap = $this->visibilityProvider->getActiveMap(
            'option',
            'magento',
            $codes,
            (string)$attributeMapping['magento_attribute_code']
        );

        foreach ($options['by_id'] as $optionId => $option) {
            $result['by_id'][$optionId]['active'] = $activeMap['option_' . $optionId] ?? true;
            if (!$result['by_id'][$optionId]['active']) {
                continue;
            }
            $key = $this->matchKeyResolver->resolve(
                $attributeMapping,
                [
                'label' => (string)($option['label'] ?? ''),
                'code' => 'option_' . $optionId,
                'scope' => 'ID ' . $optionId,
                'type' => 'option',
                ],
                'magento'
            );
            if ($key !== '') {
                $result['by_match'][$key][] = (int)$optionId;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed> $attributeMapping
     * @return array{
     *     by_id: array<int, array<string, mixed>>,
     *     by_match: array<string, array<int, int>>
     * }
     */
    private function loadNativeOptions(string $attributeCode, array $attributeMapping): array
    {
        $result = ['by_id' => [], 'by_match' => []];

        foreach ($this->magentoOptionProvider->getOptions($attributeCode) as $option) {
            if (!preg_match('/^option_(\d+)$/', (string)($option['code'] ?? ''), $matches)) {
                continue;
            }
            $optionId = (int)$matches[1];
            $result['by_id'][$optionId] = [
                'option_id' => $optionId,
                'sort_order' => 0,
                'label' => (string)($option['label'] ?? ''),
            ];
            if (empty($option['active'])) {
                continue;
            }
            $key = $this->matchKeyResolver->resolve($attributeMapping, $option, 'magento');
            if ($key !== '') {
                $result['by_match'][$key][] = $optionId;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function activeErgonodeOptions(array $rows, string $attributeCode): array
    {
        $codes = array_map(
            static fn (array $row): string => (string)$row['option_code'],
            $rows
        );
        $activeMap = $this->visibilityProvider->getActiveMap(
            'option',
            'ergo',
            $codes,
            $attributeCode
        );

        return array_values(
            array_filter(
                $rows,
                static fn (array $row): bool => $activeMap[(string)$row['option_code']] ?? true
            )
        );
    }

    /**
     * @param mixed $attribute
     */
    private function canSyncMagentoAttribute($attribute): bool
    {
        if (!in_array((string)$attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            return false;
        }

        $sourceModel = (string)$attribute->getSourceModel();
        if ($sourceModel === '') {
            return true;
        }

        try {
            return $attribute->getSource() instanceof Table;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, array<string, mixed>> $mappings
     * @return array<int, array<string, mixed>>
     */
    private function keyMappingsByOptionId(array $mappings): array
    {
        $result = [];
        foreach ($mappings as $mapping) {
            $optionId = isset($mapping['magento_option_id']) ? (int)$mapping['magento_option_id'] : 0;
            if ($optionId > 0) {
                $result[$optionId] = $mapping;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>                $row
     * @param array{
     *     by_id: array<int, array<string, mixed>>,
     *     by_match: array<string, array<int, int>>
     * } $magentoOptions
     * @param array<string, array<string, mixed>> $existingMappings
     * @param array<int, array<string, mixed>>    $existingMappingsByOptionId
     */
    private function resolveMagentoOptionId(
        array $row,
        string $defaultLabel,
        array $attributeMapping,
        array $magentoOptions,
        array $existingMappings,
        array $existingMappingsByOptionId,
        ?array $plannedIds = null
    ): ?int {
        $optionCode = (string)$row['option_code'];
        $candidates = [
            isset($existingMappings[$optionCode]['magento_option_id'])
                ? (int)$existingMappings[$optionCode]['magento_option_id']
                : 0,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate > 0 && isset($magentoOptions['by_id'][$candidate])) {
                return $candidate;
            }
        }

        if ($plannedIds !== null) {
            return $plannedIds[$optionCode] ?? null;
        }

        $matchKey = $this->matchKeyResolver->resolve(
            $attributeMapping,
            [
            'label' => $defaultLabel,
            'code' => $optionCode,
            'scope' => '',
            'type' => 'option',
            ],
            'ergonode'
        );
        foreach ($magentoOptions['by_match'][$matchKey] ?? [] as $candidate) {
            $collision = $existingMappingsByOptionId[$candidate] ?? null;
            if ($collision === null || (string)$collision['ergonode_option_code'] === $optionCode) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $magentoOptions
     * @param array<string, array<string, mixed>> $existingMappings
     * @return array{matched_ids: array<string, int>, conflicts: array<string, string>}
     */
    private function planTableMatches(
        array $mapping,
        array $rows,
        array $magentoOptions,
        array $existingMappings
    ): array {
        $left = [];
        foreach ($rows as $row) {
            $left[] = [
                'code' => (string)$row['option_code'],
                'type' => 'option',
                'names' => $this->labelResolver->decode((string)$row['labels_json']),
            ];
        }
        $right = [];
        foreach ($magentoOptions['by_id'] as $id => $option) {
            if (empty($option['active'])) {
                continue;
            }
            $right[] = [
                'code' => 'option_' . $id,
                'type' => 'option',
                'label' => (string)($option['label'] ?? ''),
                'store_labels' => (array)($option['store_labels'] ?? []),
            ];
        }
        $savedPairs = [];
        foreach ($existingMappings as $code => $row) {
            $id = (int)($row['magento_option_id'] ?? 0);
            if ($id > 0 && ($row['status'] ?? 'complete') === 'complete') {
                $savedPairs[$code] = $id;
            }
        }
        $result = $this->optionPairPlanner->plan(
            $mapping,
            $left,
            $right,
            $this->labelResolver->getLanguageStoreMap(),
            $savedPairs
        );
        $matchedIds = [];
        foreach ($result['matches'] as $pair) {
            $matchedIds[(string)$pair['left']['code']] = (int)substr((string)$pair['right']['code'], 7);
        }

        return ['matched_ids' => $matchedIds, 'conflicts' => $result['conflicts']];
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'created' => 0,
            'linked' => 0,
            'mappings_inserted' => 0,
            'mappings_updated' => 0,
            'labels_updated' => 0,
            'sort_order_updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }
}
