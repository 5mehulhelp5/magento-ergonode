<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Test\Integration\Model;

use Ergonode\CategoryAttributeHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\App\ResourceConnection;
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
        $table = $resource->getTableName('ergonode_category_attribute_history_operation');
        $connection->delete($table);
        $row = [
            'operation_code' => 'save', 'status' => 'success', 'origin' => 'cli',
            'started_at' => '2026-08-01 00:00:00', 'finished_at' => '2026-08-11 23:59:59',
            'change_count' => 0, 'state_json' => '{}', 'changes_json' => '[]',
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
}
