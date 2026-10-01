<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Unit\Setup;

use Ergonode\Product\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOwnedMappingTable(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::once())->method('startSetup');
        $deleted = [];
        $connection->expects(self::exactly(2))->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deleted): int {
                self::assertSame('prefix_core_config_data', $table);
                $deleted[] = $where['path = ?'];
                return 1;
            });
        $connection->expects(self::once())->method('dropTable')->with('prefix_ergonode_product_mapping');
        $connection->expects(self::once())->method('endSetup');

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
        self::assertSame([
            'ergonode_products/identity/sku_mode',
            'ergonode_products/identity/magento_attribute',
        ], $deleted);
    }

    public function testToleratesMissingTables(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('dropTable');

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
