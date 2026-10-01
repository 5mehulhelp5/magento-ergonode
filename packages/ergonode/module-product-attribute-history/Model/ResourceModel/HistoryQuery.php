<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model\ResourceModel;

use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class HistoryQuery implements HistoryQueryInterface
{
    private const string TABLE = 'ergonode_product_attribute_history_operation';
    private const array COLUMNS = [
        'operation_id', 'operation_code', 'status', 'origin', 'actor_id', 'actor_name',
        'started_at', 'finished_at', 'change_count',
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    public function getOperations(int $limit = 10, ?int $beforeOperationId = null): array
    {
        $limit = max(1, min(100, $limit));
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $select = $connection->select()->from($table, self::COLUMNS)
            ->order('operation_id DESC')->limit($limit + 1);
        if ($beforeOperationId !== null) {
            $select->where('operation_id < ?', $beforeOperationId);
        }
        $rows = $connection->fetchAll($select);

        return [
            'items' => array_values(array_map($this->normalize(...), array_slice($rows, 0, $limit))),
            'total' => (int)$connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])),
            'has_more' => count($rows) > $limit,
        ];
    }

    public function getState(int $operationId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resourceConnection->getTableName(self::TABLE))
                ->where('operation_id = ?', $operationId)
        );
        if (!$row) {
            return null;
        }
        $state = $this->json->unserialize((string)$row['state_json']);
        $changes = $this->json->unserialize((string)$row['changes_json']);
        unset($row['state_json'], $row['changes_json']);

        return [
            'operation' => $this->normalize($row),
            'source' => $state['source'],
            'target' => $state['target'],
            'options' => $state['options'] ?? null,
            'changes' => $changes,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $row['operation_id'] = (int)$row['operation_id'];
        $row['change_count'] = (int)$row['change_count'];
        $row['actor_id'] = $row['actor_id'] === null ? null : (int)$row['actor_id'];

        return $row;
    }
}
