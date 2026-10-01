<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Test\Unit\Setup;

use Ergonode\CategoryAttribute\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testOnlyNeutralMappingsAndTheirVisibilityAreRemoved(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $name): string => 'prefix_' . $name);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deletes): int {
                $deletes[$table] = $where;
                return 1;
            }
        );
        $dropped = [];
        $connection->expects(self::exactly(2))->method('dropTable')->willReturnCallback(
            static function (string $table) use (&$dropped): void {
                $dropped[] = $table;
            }
        );
        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(
            ['entity_type IN (?)' => ['category_attribute', 'category_option']],
            $deletes['prefix_ergonode_mapping_visibility']
        );
        self::assertSame([
            'Ergonode_CategoryConsumer::category_attribute_mapping',
            'Ergonode_CategoryConsumer::category_attribute_refresh',
            'Ergonode_CategoryConsumer::category_attribute_save',
        ], $deletes['prefix_authorization_rule']['resource_id IN (?)']);
        self::assertSame([
            'prefix_ergonode_category_option_mapping', 'prefix_ergonode_category_attribute_mapping',
        ], $dropped);
    }

    public function testRepeatedUninstallToleratesMissingTables(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');
        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
