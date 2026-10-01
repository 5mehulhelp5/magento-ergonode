<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model\ResourceModel;

use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\App\ResourceConnection;

class HistoryPruner implements HistoryPrunerInterface
{
    private const int BATCH_SIZE = 500;

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function deleteBefore(string $cutoff): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_attribute_history_operation');
        $select = $connection->select()->from($table, ['operation_id'])
            ->where('finished_at < ?', $cutoff)
            ->order('operation_id ASC')->limit(self::BATCH_SIZE);

        $deleted = 0;
        do {
            $ids = $connection->fetchCol($select);
            if ($ids !== []) {
                $deleted += $connection->delete($table, ['operation_id IN (?)' => $ids]);
            }
        } while (count($ids) === self::BATCH_SIZE);

        return $deleted;
    }
}
