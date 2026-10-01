<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use InvalidArgumentException;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class CategoryCacheProvider
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $rowsCache = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    /**
     * @return array<int, array{
     *     entity_id: int,
     *     category_tree_id: int,
     *     code: string,
     *     ergonode_category_id: string|null,
     *     parent_code: string|null,
     *     manual_parent_code: string|null,
     *     effective_parent_code: string|null,
     *     sort_order: int,
     *     manual_sort_order: int|null,
     *     effective_sort_order: int,
     *     magento_category_id: int|null,
     *     labels: array<string, string>,
     *     label: string,
     *     sync_status: string,
     *     sync_message: string|null
     * }>
     */
    public function getRows(int $categoryTreeId): array
    {
        if ($categoryTreeId <= 0) {
            return [];
        }

        if (isset($this->rowsCache[$categoryTreeId])) {
            return $this->rowsCache[$categoryTreeId];
        }

        $connection = $this->resourceConnection->getConnection();
        $snapshotTable = $this->resourceConnection->getTableName('ergonode_category_snapshot');
        $mappingTable = $this->resourceConnection->getTableName('ergonode_category_mapping');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['snapshot' => $snapshotTable])
                ->joinLeft(
                    ['mapping' => $mappingTable],
                    'mapping.category_tree_id = snapshot.category_tree_id'
                    . ' AND mapping.ergonode_category_code = snapshot.category_code',
                    [
                        'mapping_id',
                        'ergonode_category_id',
                        'manual_parent_code',
                        'manual_sort_order',
                        'magento_category_id',
                        'sync_status',
                        'sync_message',
                    ]
                )
                ->where('snapshot.category_tree_id = ?', $categoryTreeId)
                ->order('COALESCE(mapping.manual_sort_order, snapshot.sort_order) ASC')
                ->order('snapshot.category_code ASC')
        );
        $result = [];

        foreach ($rows as $row) {
            $labels = $this->decodeLabels((string)$row['labels_json']);
            $manualSortOrder = $row['manual_sort_order'] !== null ? (int)$row['manual_sort_order'] : null;
            $magentoCategoryId = $row['magento_category_id'] !== null ? (int)$row['magento_category_id'] : null;
            $parentCode = $this->nullableString($row['parent_category_code'] ?? null);
            $manualParentCode = $this->nullableString($row['manual_parent_code'] ?? null);

            $result[] = [
                'entity_id' => (int)$row['snapshot_id'],
                'category_tree_id' => (int)$row['category_tree_id'],
                'code' => (string)$row['category_code'],
                'ergonode_category_id' => $this->nullableString($row['ergonode_category_id'] ?? null),
                'parent_code' => $parentCode,
                'manual_parent_code' => $manualParentCode,
                'effective_parent_code' => $manualParentCode ?? $parentCode,
                'sort_order' => (int)$row['sort_order'],
                'manual_sort_order' => $manualSortOrder,
                'effective_sort_order' => $manualSortOrder ?? (int)$row['sort_order'],
                'magento_category_id' => $magentoCategoryId && $magentoCategoryId > 0 ? $magentoCategoryId : null,
                'labels' => $labels,
                'label' => $this->resolveLabel($labels, (string)$row['category_code']),
                'sync_status' => (string)($row['sync_status'] ?? 'pending'),
                'sync_message' => $this->nullableString($row['sync_message'] ?? null),
            ];
        }

        return $this->rowsCache[$categoryTreeId] = $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRowsByCode(int $categoryTreeId): array
    {
        $rows = [];
        foreach ($this->getRows($categoryTreeId) as $row) {
            $rows[(string)$row['code']] = $row;
        }

        return $rows;
    }

    public function clearCache(): void
    {
        $this->rowsCache = [];
    }

    /**
     * @return array<string, string>
     */
    private function decodeLabels(string $labelsJson): array
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            return [];
        }

        if (!is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $language => $label) {
            $language = trim((string)$language);
            $label = trim((string)$label);
            if ($language !== '' && $label !== '') {
                $result[$language] = $label;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $labels
     */
    private function resolveLabel(array $labels, string $fallback): string
    {
        $defaultLocale = $this->languageMappingProvider->getAdminLanguageCode();

        return (string)(($defaultLocale !== null ? $labels[$defaultLocale] ?? null : null)
            ?? reset($labels)
            ?: $fallback);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
