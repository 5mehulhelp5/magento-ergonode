<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Magento\Framework\App\ResourceConnection;

class CategoryFormContextProvider implements CategoryFormContextProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CategoryTreeQuery $categoryTreeQuery
    ) {
    }

    public function getForMagentoCategory(int $categoryId): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }

        $path = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('catalog_category_entity'), ['path'])
                ->where('entity_id = ?', $categoryId)
                ->limit(1)
        );
        $rootCategoryId = $this->rootCategoryId(is_string($path) ? $path : '');
        if ($rootCategoryId <= 0) {
            return null;
        }

        $categoryTreeId = $this->categoryTreeQuery->findIdByRootCategoryId($rootCategoryId);
        if ($categoryTreeId === null) {
            return null;
        }
        $categoryTree = $this->categoryTreeQuery->getById($categoryTreeId);
        if (empty($categoryTree['is_active'])) {
            return null;
        }

        return [
            'category_tree_id' => $categoryTreeId,
            'root_category_id' => $rootCategoryId,
            'ergonode_category_code' => $this->getValidCode($categoryTreeId, $categoryId),
        ];
    }

    private function rootCategoryId(string $path): int
    {
        $pathIds = array_values(array_filter(array_map('intval', explode('/', $path))));

        return count($pathIds) >= 2 && $pathIds[0] === 1 ? $pathIds[1] : 0;
    }

    private function getValidCode(int $categoryTreeId, int $categoryId): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => trim((string)$code),
            $connection->fetchCol(
                $connection->select()
                    ->from(
                        ['mapping' => $this->resourceConnection->getTableName('ergonode_category_mapping')],
                        ['ergonode_category_code']
                    )
                    ->joinInner(
                        ['snapshot' => $this->resourceConnection->getTableName('ergonode_category_snapshot')],
                        'snapshot.category_tree_id = mapping.category_tree_id'
                        . ' AND snapshot.category_code = mapping.ergonode_category_code',
                        []
                    )
                    ->where('mapping.category_tree_id = ?', $categoryTreeId)
                    ->where('mapping.magento_category_id = ?', $categoryId)
                    ->order('mapping.mapping_id ASC')
            )
        ), static fn (string $code): bool => $code !== '')));

        return count($codes) === 1 ? $codes[0] : null;
    }
}
