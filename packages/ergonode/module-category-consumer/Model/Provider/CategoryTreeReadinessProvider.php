<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Provider;

use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Magento\Framework\App\ResourceConnection;

class CategoryTreeReadinessProvider implements CategoryTreeReadinessProviderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getActiveTrees(?string $treeCode = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['tree' => $this->resourceConnection->getTableName('ergonode_category_tree')],
                ['category_tree_id', 'tree_code', 'root_category_id']
            )
            ->joinLeft(
                ['root' => $this->resourceConnection->getTableName('catalog_category_entity')],
                'root.entity_id = tree.root_category_id',
                ['existing_root_id' => 'root.entity_id']
            )
            ->where('tree.is_active = ?', 1)
            ->order('tree.sort_order ASC')
            ->order('tree.category_tree_id ASC');
        $treeCode = trim((string)$treeCode);
        if ($treeCode !== '') {
            $select->where('tree.tree_code = ?', $treeCode);
        }

        return array_map(
            static fn (array $row): array => [
                'category_tree_id' => (int)$row['category_tree_id'],
                'tree_code' => (string)$row['tree_code'],
                'root_category_id' => (int)$row['root_category_id'],
                'root_exists' => (int)($row['existing_root_id'] ?? 0) > 0,
            ],
            $connection->fetchAll($select)
        );
    }
}
