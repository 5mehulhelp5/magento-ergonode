<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup\Uninstall;

use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testDropsOnlyOwnedTablesAndNeverDeletesMagentoRecords(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::never())->method('delete');
        $tables = [
            'prefix_ergonode_media_scan',
            'prefix_ergonode_media_local_file',
            'prefix_ergonode_media_state',
            'prefix_ergonode_media_file_usage',
            'prefix_ergonode_media_product_work',
            'prefix_ergonode_media_product_usage',
            'prefix_ergonode_media_materialization',
            'prefix_ergonode_media_asset',
        ];
        $connection->expects(self::exactly(count($tables)))
            ->method('dropTable')
            ->willReturnCallback(static function (string $table) use (&$tables): bool {
                self::assertSame(array_shift($tables), $table);

                return true;
            });

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([], $tables);
    }

    public function testMissingOwnedTablesDoNotTriggerCleanupInMagentoTables(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');
        (new RuntimeRecordCleaner())->execute($setup);
    }
}
