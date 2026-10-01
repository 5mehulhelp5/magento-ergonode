<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Throwable;

class HistoryWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param array{origin: string, actor_id: int|null, actor_name: string|null} $context
     * @param array<string, int|string|bool|null> $operationSummary
     * @param list<array{
     *     tree: array<string, int|string|bool>,
     *     before_hash: string,
     *     after_hash: string,
     *     summary: array<string, int>,
     *     changes: list<array<string, mixed>>
     * }> $changeSets
     */
    public function save(
        string $operationCode,
        string $status,
        string $startedAt,
        array $context,
        array $operationSummary,
        array $changeSets
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $operationId = $this->start($operationCode, $startedAt, $context);
            $this->append($operationId, $changeSets);
            $this->finish($operationId, $status, $operationSummary);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @param array{origin: string, actor_id: int|null, actor_name: string|null} $context */
    public function start(string $operationCode, string $startedAt, array $context): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_history_operation');
        $connection->insert($table, [
            'operation_code' => $operationCode, 'origin' => $context['origin'], 'status' => 'running',
            'actor_id' => $context['actor_id'], 'actor_name' => $context['actor_name'],
            'started_at' => $startedAt, 'finished_at' => null,
        ]);

        return (int)$connection->fetchOne(
            $connection->select()->from($table, [new Expression('LAST_INSERT_ID()')])->limit(1)
        );
    }

    /** @param array<string, int|string|bool|null> $summary */
    public function finish(int $operationId, string $status, array $summary): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName('ergonode_category_history_operation'),
            ['status' => $status, 'summary_json' => $this->json->serialize($summary),
                'finished_at' => $this->dateTime->gmtDate()],
            ['operation_id = ?' => $operationId]
        );
    }

    /** @param list<array<string, mixed>> $changeSets */
    public function append(int $operationId, array $changeSets): void
    {
        $connection = $this->resourceConnection->getConnection();
        $setTable = $this->resourceConnection->getTableName('ergonode_category_history_change_set');
        $changeTable = $this->resourceConnection->getTableName('ergonode_category_history_change');
        $finishedAt = $this->dateTime->gmtDate();
        $connection->beginTransaction();
        try {
            foreach ($changeSets as $changeSet) {
                $tree = $changeSet['tree'];
                $connection->insert($setTable, [
                    'operation_id' => $operationId,
                    'category_tree_id' => (int)$tree['category_tree_id'],
                    'tree_code' => (string)$tree['tree_code'],
                    'root_category_id' => (int)$tree['root_category_id'],
                    'root_label' => (string)$tree['root_label'],
                    'before_hash' => $changeSet['before_hash'],
                    'after_hash' => $changeSet['after_hash'],
                    'summary_json' => $this->json->serialize($changeSet['summary']),
                    'created_at' => $finishedAt,
                ]);
                $changeSetId = (int)$connection->fetchOne(
                    $connection->select()->from($setTable, [new Expression('LAST_INSERT_ID()')])->limit(1)
                );
                foreach (array_chunk($changeSet['changes'], 500) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $change) {
                        $rows[] = [
                            'change_set_id' => $changeSetId,
                            'entity_type' => (string)$change['entity_type'],
                            'entity_identifier' => (string)$change['entity_identifier'],
                            'category_code' => $change['category_code'],
                            'actions_json' => $this->json->serialize($change['actions']),
                            'before_state' => $change['before'] !== null
                                ? $this->json->serialize($change['before']) : null,
                            'after_state' => $change['after'] !== null
                                ? $this->json->serialize($change['after']) : null,
                            'created_at' => $finishedAt,
                        ];
                    }
                    $connection->insertMultiple($changeTable, $rows);
                }
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
