<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class HistoryReader
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function getOperations(int $categoryTreeId, int $limit, ?int $beforeOperationId = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['history_set' => $this->resourceConnection->getTableName('ergonode_category_history_change_set')],
                ['change_set_id', 'set_summary_json' => 'summary_json']
            )
            ->joinInner(
                ['history_operation' => $this->resourceConnection->getTableName('ergonode_category_history_operation')],
                'history_operation.operation_id = history_set.operation_id',
                [
                    'operation_id',
                    'operation_code',
                    'origin',
                    'status',
                    'actor_name',
                    'operation_summary_json' => 'summary_json',
                    'started_at',
                    'finished_at',
                ]
            )
            ->where('history_set.category_tree_id = ?', $categoryTreeId)
            ->order('history_operation.operation_id DESC');
        if ($beforeOperationId !== null) {
            $select->where('history_operation.operation_id < ?', $beforeOperationId);
        }
        $select->limit($limit);

        return $connection->fetchAll($select);
    }

    public function countOperations(int $categoryTreeId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('ergonode_category_history_change_set'),
                ['COUNT(*)']
            )
            ->where('category_tree_id = ?', $categoryTreeId);

        return (int)$connection->fetchOne($select);
    }

    /** @return array<string, mixed>|null */
    public function getChangeSet(int $categoryTreeId, int $operationId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_history_change_set'))
            ->where('category_tree_id = ?', $categoryTreeId)
            ->where('operation_id = ?', $operationId)
            ->limit(1);
        $row = $connection->fetchRow($select);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function getOperation(int $operationId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_history_operation'))
            ->where('operation_id = ?', $operationId)
            ->limit(1);
        $row = $connection->fetchRow($select);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function getChanges(int $changeSetId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_history_change'))
            ->where('change_set_id = ?', $changeSetId)
            ->order('change_id ASC');

        return $connection->fetchAll($select);
    }

    /** @return iterable<array<string, mixed>> */
    public function getChangesAfter(int $categoryTreeId, int $operationId): iterable
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['history_change' => $this->resourceConnection->getTableName('ergonode_category_history_change')]
            )
            ->joinInner(
                ['history_set' => $this->resourceConnection->getTableName('ergonode_category_history_change_set')],
                'history_set.change_set_id = history_change.change_set_id',
                ['replay_operation_id' => 'operation_id']
            )
            ->where('history_set.category_tree_id = ?', $categoryTreeId)
            ->where('history_set.operation_id > ?', $operationId)
            ->order(['history_set.operation_id DESC', 'history_change.change_id DESC']);

        $lastOperation = null;
        $lastChange = null;
        do {
            $page = clone $select;
            if ($lastOperation !== null) {
                $page->where(
                    $connection->quoteInto('history_set.operation_id < ?', $lastOperation)
                    . ' OR (' . $connection->quoteInto('history_set.operation_id = ?', $lastOperation)
                    . ' AND ' . $connection->quoteInto('history_change.change_id < ?', $lastChange) . ')'
                );
            }
            $rows = $connection->fetchAll($page->limit(500));
            foreach ($rows as $row) {
                $lastOperation = (int)$row['replay_operation_id'];
                $lastChange = (int)$row['change_id'];
                unset($row['replay_operation_id']);
                yield $row;
            }
        } while (count($rows) === 500);
    }
}
