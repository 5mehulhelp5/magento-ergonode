<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Integration\Model;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryQuery;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Persistence\HistoryPrunerInterface;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryReader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class HistoryPrunerTest extends TestCase
{
    public function testDefaultsAreEnabledAndThirtyDays(): void
    {
        $config = Bootstrap::getObjectManager()->get(HistoryConfig::class);
        self::assertTrue($config->isEnabled());
        self::assertTrue($config->isCleanupEnabled());
        self::assertSame(30, $config->getRetentionDays());
    }

    public function testDeletesExpiredOperationsInBatchesAndPreservesCutoff(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_category_history_operation');
        $connection->delete($table);
        $row = [
            'operation_code' => 'save', 'status' => 'success', 'origin' => 'cli',
            'started_at' => '2026-08-01 00:00:00', 'finished_at' => '2026-08-11 23:59:59',
            'summary_json' => '{}',
        ];
        $connection->insertMultiple($table, array_fill(0, 501, $row));
        $row['finished_at'] = '2026-08-12 00:00:00';
        $connection->insert($table, $row);
        $retainedId = (int)$connection->lastInsertId($table);
        $pruner = $objects->get(HistoryPrunerInterface::class);

        self::assertSame(501, $pruner->deleteBefore('2026-08-12 00:00:00'));
        self::assertSame(0, $pruner->deleteBefore('2026-08-12 00:00:00'));
        self::assertSame([$retainedId], array_map('intval', $connection->fetchCol(
            $connection->select()->from($table, ['operation_id'])
        )));
    }
    public function testPruningCascadesAndPreservesReplayWithOutOfOrderDates(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $operationTable = $resource->getTableName('ergonode_category_history_operation');
        $setTable = $resource->getTableName('ergonode_category_history_change_set');
        $changeTable = $resource->getTableName('ergonode_category_history_change');
        $connection->delete($operationTable);
        $json = $objects->get(Json::class);
        $ids = [];
        foreach (['2026-08-01 00:00:00', '2026-09-01 00:00:00', '2026-08-02 00:00:00'] as $index => $date) {
            $connection->insert($operationTable, [
                'operation_code' => 'save', 'status' => 'success', 'origin' => 'cli',
                'started_at' => $date, 'finished_at' => $date,
            ]);
            $ids[] = $operationId = (int)$connection->lastInsertId($operationTable);
            $connection->insert($setTable, [
                'operation_id' => $operationId, 'category_tree_id' => 999001, 'tree_code' => 'retention',
                'root_category_id' => 2, 'root_label' => 'Root', 'before_hash' => str_repeat('a', 64),
                'after_hash' => str_repeat('b', 64), 'summary_json' => '{}',
            ]);
            $setId = (int)$connection->lastInsertId($setTable);
            $connection->insert($changeTable, [
                'change_set_id' => $setId, 'entity_type' => 'source', 'entity_identifier' => 'chairs',
                'actions_json' => '["renamed"]',
                'before_state' => $json->serialize(['identifier' => 'chairs', 'label' => 'Label ' . $index]),
                'after_state' => $json->serialize(['identifier' => 'chairs', 'label' => 'Label ' . ($index + 1)]),
            ]);
        }
        $state = $this->createStub(CategoryTreeStateProviderInterface::class);
        $state->method('getState')->willReturn([
            'tree' => ['category_tree_id' => 999001],
            'source' => [['identifier' => 'chairs', 'label' => 'Label 3']], 'target' => [],
        ]);
        $query = new CategoryTreeHistoryQuery(
            $state,
            $objects->get(HistoryReader::class),
            $json,
            $objects->get(CategorySynchronizationLock::class)
        );
        $before = $query->getState(999001, $ids[1]);
        $pruner = $objects->get(HistoryPrunerInterface::class);
        self::assertSame(1, $pruner->deleteBefore('2026-08-12 00:00:00'));
        self::assertSame(0, $pruner->deleteBefore('2026-08-12 00:00:00'));
        self::assertSame($before, $query->getState(999001, $ids[1]));
        self::assertSame('Label 2', $before['source'][0]['label']);
        self::assertSame([$ids[1], $ids[2]], array_map('intval', $connection->fetchCol(
            $connection->select()->from($operationTable, ['operation_id'])->order('operation_id')
        )));
        self::assertSame(2, (int)$connection->fetchOne($connection->select()->from($setTable, ['COUNT(*)'])));
        self::assertSame(2, (int)$connection->fetchOne($connection->select()->from($changeTable, ['COUNT(*)'])));
        self::assertSame(2, $pruner->deleteBefore('2026-09-02 00:00:00'));
        self::assertSame(0, (int)$connection->fetchOne($connection->select()->from($setTable, ['COUNT(*)'])));
        self::assertSame(0, (int)$connection->fetchOne($connection->select()->from($changeTable, ['COUNT(*)'])));
    }

    public function testUnfinishedOperationProtectsLaterDeltas(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_category_history_operation');
        $connection->delete($table);
        foreach (['2026-08-01 00:00:00', null, '2026-08-02 00:00:00'] as $finishedAt) {
            $connection->insert($table, [
                'operation_code' => 'save', 'status' => 'success', 'origin' => 'cli',
                'started_at' => '2026-08-01 00:00:00', 'finished_at' => $finishedAt,
            ]);
        }
        self::assertSame(1, $objects->get(HistoryPrunerInterface::class)->deleteBefore('2026-08-12 00:00:00'));
        self::assertSame(2, (int)$connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])));
    }
}
