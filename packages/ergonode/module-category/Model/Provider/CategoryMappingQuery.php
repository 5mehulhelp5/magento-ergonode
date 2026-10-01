<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Magento\Framework\App\ResourceConnection;

class CategoryMappingQuery
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @return array<int, array{code: string, parent_code: string|null, magento_category_id: int|null}> */
    public function getPreparedLayoutByTreeId(int $categoryTreeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        return $connection->fetchAll(
            $connection->select()->from(
                $this->resourceConnection->getTableName('ergonode_category_mapping'),
                [
                    'code' => 'ergonode_category_code',
                    'parent_code' => 'manual_parent_code',
                    'magento_category_id',
                ]
            )->where('category_tree_id = ?', $categoryTreeId)
                ->where('ergonode_category_id IS NOT NULL')
        );
    }

    /**
     * @param string[] $codes
     * @return array<string, array<int, array{category_tree_id: int, magento_category_id: int}>>
     */
    public function getValidMappingsByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map('trim', $codes),
            static fn (string $code): bool => $code !== ''
        )));
        if ($codes === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['mapping' => $this->resourceConnection->getTableName('ergonode_category_mapping')], [
                    'category_tree_id' => 'mapping.category_tree_id',
                    'ergonode_category_code' => 'mapping.ergonode_category_code',
                    'magento_category_id' => 'mapping.magento_category_id',
                ])
                ->joinInner(
                    ['tree' => $this->resourceConnection->getTableName('ergonode_category_tree')],
                    'tree.category_tree_id = mapping.category_tree_id',
                    []
                )
                ->where('mapping.ergonode_category_code IN (?)', $codes)
                ->where('mapping.magento_category_id IS NOT NULL')
                ->where('tree.is_active = ?', 1)
        );
        $result = [];
        foreach ($rows as $row) {
            $categoryId = (int)$row['magento_category_id'];
            if ($categoryId <= 0) {
                continue;
            }
            $result[(string)$row['ergonode_category_code']][] = [
                'category_tree_id' => (int)$row['category_tree_id'],
                'magento_category_id' => $categoryId,
            ];
        }

        return $result;
    }

    /** @return array<string, int> */
    public function getMappingsByTreeId(int $categoryTreeId): array
    {
        if ($categoryTreeId <= 0) {
            return [];
        }
        $rows = $this->resourceConnection->getConnection()->fetchPairs(
            $this->resourceConnection->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_category_mapping'),
                    ['ergonode_category_code', 'magento_category_id']
                )
                ->where('category_tree_id = ?', $categoryTreeId)
                ->where('magento_category_id IS NOT NULL')
        );
        $result = [];
        foreach ($rows as $code => $categoryId) {
            $code = trim((string)$code);
            $categoryId = (int)$categoryId;
            if ($code !== '' && $categoryId > 0) {
                $result[$code] = $categoryId;
            }
        }

        return $result;
    }
}
