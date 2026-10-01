<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Setup;

use Ergonode\Core\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesSharedStorageAndCoreAclRules(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deleted = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $condition) use (&$deleted): int {
                $deleted[$table] = $condition;
                return 1;
            }
        );
        $droppedTables = [];
        $connection->expects(self::exactly(2))->method('dropTable')->willReturnCallback(
            static function (string $table) use (&$droppedTables): void {
                $droppedTables[] = $table;
            }
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertCount(6, $deleted['prefix_authorization_rule']['resource_id IN (?)']);
        self::assertSame([
            'ergonode_connection/test/url',
            'ergonode_connection/general/environment',
            'ergonode_connection/production/url',
            'ergonode_connection/general/mode',
            'ergonode_connection/general/enabled',
            'ergonode_connection/test/requests_per_minute',
            'ergonode_connection/production/requests_per_minute',
        ], $deleted['prefix_core_config_data']['path IN (?)']);
        self::assertSame([
            'prefix_ergonode_mapping_visibility',
            'prefix_ergonode_import_cursor',
        ], $droppedTables);
    }
}
