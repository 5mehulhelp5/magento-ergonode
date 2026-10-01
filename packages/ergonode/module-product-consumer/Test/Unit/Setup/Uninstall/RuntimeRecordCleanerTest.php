<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Setup\Uninstall;

use Ergonode\ProductConsumer\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesOwnedQueueCronConfigurationCursorAndWorkRecords(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->expects(self::exactly(2))->method('select')->willReturn($select);
        $connection->expects(self::exactly(2))->method('fetchCol')->willReturnOnConsecutiveCalls([11], [22]);
        $deletes = [
            ['prefix_queue_message_status', ['message_id IN (?)' => [11]]],
            ['prefix_queue_message_status', ['queue_id IN (?)' => [22]]],
            ['prefix_queue_message', ['topic_name = ?' => 'ergonode.product.import']],
            ['prefix_queue', ['name = ?' => 'ergonode.product.import']],
            ['prefix_cron_schedule', ['job_code IN (?)' => [
                'ergonode_product_import_schedule',
                'ergonode_product_import_recovery',
            ]]],
            ['prefix_core_config_data', ['path LIKE ?' => 'ergonode_products/import/%']],
            ['prefix_ergonode_import_cursor', ['process_code IN (?)' => [
                'product_stream',
                'product_deleted_stream',
            ]]],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });
        $connection->expects(self::once())->method('dropTable')->with('prefix_ergonode_product_import_item');

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([], $deletes);
    }

    public function testToleratesCompletelyMissingRuntimeSchema(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('select');
        $connection->expects(self::never())->method('fetchCol');
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');

        (new RuntimeRecordCleaner())->execute($setup);
    }
}
