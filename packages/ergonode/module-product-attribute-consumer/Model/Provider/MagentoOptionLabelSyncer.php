<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Provider;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class MagentoOptionLabelSyncer
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    /**
     * @param array<int, array<string, string>> $labelsByOptionId
     * @return array<int, int> Number of changed label rows keyed by option ID.
     * @throws LocalizedException
     */
    public function syncLabelsBatch(int $attributeId, array $labelsByOptionId): array
    {
        $labelsByOptionId = array_filter(
            $labelsByOptionId,
            static fn (array $labels, int $optionId): bool => $optionId > 0 && $labels !== [],
            ARRAY_FILTER_USE_BOTH
        );
        if ($attributeId <= 0 || $labelsByOptionId === []) {
            return [];
        }

        $connection = $this->getConnection();
        $optionIds = array_keys($labelsByOptionId);
        $validOptionIds = array_map(
            'intval',
            $connection->fetchCol(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('eav_attribute_option'), ['option_id'])
                    ->where('attribute_id = ?', $attributeId)
                    ->where('option_id IN (?)', $optionIds)
            )
        );
        $missingOptionIds = array_values(array_diff($optionIds, $validOptionIds));
        if ($missingOptionIds !== []) {
            throw new LocalizedException(
                __(
                    'Magento options "%1" do not belong to attribute ID "%2".',
                    implode(', ', $missingOptionIds),
                    $attributeId
                )
            );
        }

        $storeLabelsByOptionId = [];
        $storeIds = [];
        foreach ($labelsByOptionId as $optionId => $labels) {
            $storeLabels = $this->resolveStoreLabels($labels);
            if ($storeLabels === []) {
                continue;
            }

            $storeLabelsByOptionId[$optionId] = $storeLabels;
            $storeIds = array_merge($storeIds, array_keys($storeLabels));
        }
        if ($storeLabelsByOptionId === []) {
            return [];
        }

        $table = $this->resourceConnection->getTableName('eav_attribute_option_value');
        $existing = [];
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['option_id', 'store_id', 'value'])
                ->where('option_id IN (?)', array_keys($storeLabelsByOptionId))
                ->where('store_id IN (?)', array_values(array_unique($storeIds)))
        );
        foreach ($rows as $row) {
            $existing[(int)$row['option_id']][(int)$row['store_id']] = (string)$row['value'];
        }

        $changes = [];
        $rowsToPersist = [];
        foreach ($storeLabelsByOptionId as $optionId => $storeLabels) {
            $changes[$optionId] = 0;
            foreach ($storeLabels as $storeId => $label) {
                if (($existing[$optionId][$storeId] ?? null) === $label) {
                    continue;
                }

                $rowsToPersist[] = [
                    'option_id' => $optionId,
                    'store_id' => $storeId,
                    'value' => $label,
                ];
                $changes[$optionId]++;
            }
        }

        if ($rowsToPersist !== []) {
            $connection->insertOnDuplicate($table, $rowsToPersist, ['value']);
        }

        return $changes;
    }

    /**
     * @param array<string, string> $labels
     * @return array<int, string>
     */
    private function resolveStoreLabels(array $labels): array
    {
        $result = [];

        foreach ($this->languageMappingProvider->getLanguageStoreMap() as $storeId => $languageCode) {
            $label = $this->resolveLabel($labels, $languageCode);
            if ($label !== '') {
                $result[(int)$storeId] = $label;
            }
        }

        return array_filter($result, static fn (string $label): bool => $label !== '');
    }

    /**
     * @param array<string, string> $labels
     */
    private function resolveLabel(array $labels, string $languageCode): string
    {
        $adminLanguageCode = $this->languageMappingProvider->getAdminLanguageCode();

        return (string)($labels[$languageCode]
            ?? ($adminLanguageCode !== null ? $labels[$adminLanguageCode] ?? null : null)
            ?: '');
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
