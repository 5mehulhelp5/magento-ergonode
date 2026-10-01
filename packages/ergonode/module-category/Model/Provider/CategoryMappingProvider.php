<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class CategoryMappingProvider implements CategoryMappingProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getCategoryCodesByMagentoIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['mapping' => $this->resourceConnection->getTableName('ergonode_category_mapping')],
                    ['magento_category_id', 'ergonode_category_code']
                )
                ->joinInner(
                    ['tree' => $this->resourceConnection->getTableName('ergonode_category_tree')],
                    'tree.category_tree_id = mapping.category_tree_id AND tree.is_active = 1',
                    []
                )
                ->joinInner(
                    ['snapshot' => $this->resourceConnection->getTableName('ergonode_category_snapshot')],
                    'snapshot.category_tree_id = mapping.category_tree_id '
                    . 'AND snapshot.category_code = mapping.ergonode_category_code',
                    []
                )
                ->where('mapping.magento_category_id IN (?)', $categoryIds)
                ->where('mapping.magento_category_id IS NOT NULL')
        );
        $result = [];
        foreach ($rows as $row) {
            $categoryId = (int)$row['magento_category_id'];
            $categoryCode = trim((string)$row['ergonode_category_code']);
            if ($categoryId <= 0 || $categoryCode === '') {
                continue;
            }
            if (isset($result[$categoryId]) && $result[$categoryId] !== $categoryCode) {
                throw new LocalizedException(__(
                    'Magento category ID "%1" has ambiguous global Ergonode category mappings.',
                    $categoryId
                ));
            }
            $result[$categoryId] = $categoryCode;
        }
        ksort($result);

        return $result;
    }

    public function getMagentoCategoryIdsByErgonodeCodes(
        array $categoryCodes,
        ?int $rootCategoryId = null
    ): array {
        $categoryCodes = array_values(array_unique(array_filter(array_map('trim', $categoryCodes))));
        if ($categoryCodes === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['mapping' => $this->resourceConnection->getTableName('ergonode_category_mapping')],
                ['ergonode_category_code', 'magento_category_id']
            )
            ->where('mapping.ergonode_category_code IN (?)', $categoryCodes)
            ->where('mapping.magento_category_id IS NOT NULL')
            ->where('mapping.sync_status = ?', 'complete');
        if ($rootCategoryId !== null) {
            $select->joinInner(
                ['tree' => $this->resourceConnection->getTableName('ergonode_category_tree')],
                'tree.category_tree_id = mapping.category_tree_id',
                []
            )
                ->where('tree.is_active = ?', 1)
                ->where('tree.root_category_id = ?', $rootCategoryId);
        }

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $categoryCode = trim((string)$row['ergonode_category_code']);
            $categoryId = (int)$row['magento_category_id'];
            if ($categoryCode !== '' && $categoryId > 0) {
                $result[$categoryCode][$categoryId] = $categoryId;
            }
        }
        foreach ($result as &$categoryIds) {
            $categoryIds = array_values($categoryIds);
            sort($categoryIds);
        }
        unset($categoryIds);
        ksort($result);

        return $result;
    }
}
