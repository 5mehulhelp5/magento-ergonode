<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Setup;

use Ergonode\Category\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testDropsOwnedTablesInForeignKeySafeOrder(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deletes): int {
                $deletes[$table] = $where;
                return 1;
            }
        );
        $droppedTables = [];
        $connection->expects(self::exactly(4))->method('dropTable')->willReturnCallback(
            static function (string $table) use (&$droppedTables): void {
                $droppedTables[] = $table;
            }
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(['entity_type = ?' => 'category'], $deletes['prefix_ergonode_mapping_visibility']);
        self::assertSame([
            'Ergonode_CategoryConsumer::category_tree_manage',
            'Ergonode_CategoryConsumer::category_tree_refresh',
            'Ergonode_CategoryConsumer::category_tree_save',
            'Ergonode_CategoryConsumer::category_tree_mapping',
            'Ergonode_CategoryConsumer::category_tree_mapping_refresh',
            'Ergonode_CategoryConsumer::category_tree_mapping_auto_map',
            'Ergonode_CategoryConsumer::category_tree_mapping_save',
        ], $deletes['prefix_authorization_rule']['resource_id IN (?)']);
        self::assertSame([
            'prefix_ergonode_category_mapping',
            'prefix_ergonode_category_snapshot',
            'prefix_ergonode_category_tree',
            'prefix_ergonode_category_tree_option',
        ], $droppedTables);
    }
}
