<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Setup\Uninstall;

use Ergonode\ProductPublisher\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesOwnedQueueCronAndPublicationTables(): void
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
            ['prefix_queue_message', ['topic_name = ?' => 'ergonode.product.publish']],
            ['prefix_queue', ['name = ?' => 'ergonode.product.publish']],
            ['prefix_cron_schedule', ['job_code = ?' => 'ergonode_product_publication_recovery']],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });
        $tables = [
            'prefix_ergonode_product_publisher_item',
            'prefix_ergonode_product_publisher_job',
        ];
        $connection->expects(self::exactly(count($tables)))
            ->method('dropTable')
            ->willReturnCallback(static function (string $table) use (&$tables): bool {
                self::assertSame(array_shift($tables), $table);

                return true;
            });

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([], $deletes);
        self::assertSame([], $tables);
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
