<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\ResourceModel;

use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryBackfillSnapshotReaderInterface;
use Magento\Framework\App\ResourceConnection;

class CategoryBackfillSnapshotReader implements CategoryBackfillSnapshotReaderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /** @return list<array{category_code: string, attributes_json: string,
     *     magento_category_id: string|int, category_tree_id: string|int}>
     */
    public function getRows(): array
    {
        $connection = $this->resourceConnection->getConnection();
        return $connection->fetchAll(
            $connection->select()
                ->from(
                    ['snapshot' => $this->resourceConnection->getTableName('ergonode_category_entity_snapshot')],
                    ['category_code', 'attributes_json']
                )
                ->join(
                    ['mapping' => $this->resourceConnection->getTableName('ergonode_category_mapping')],
                    'mapping.ergonode_category_code = snapshot.category_code',
                    ['magento_category_id', 'category_tree_id']
                )
                ->join(
                    ['source' => $this->resourceConnection->getTableName('ergonode_category_snapshot')],
                    'source.category_tree_id = mapping.category_tree_id'
                    . ' AND source.category_code = snapshot.category_code',
                    []
                )
                ->join(
                    ['tree' => $this->resourceConnection->getTableName('ergonode_category_tree')],
                    'tree.category_tree_id = mapping.category_tree_id',
                    []
                )
                ->where('mapping.magento_category_id IS NOT NULL')
                ->where('tree.is_active = ?', 1)
        );
    }
}
