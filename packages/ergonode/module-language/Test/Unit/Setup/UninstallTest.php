<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Setup;

use Ergonode\Language\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyLanguageStorageAclAndVisibility(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $deletes = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function (string $table, array $condition) use (&$deletes): int {
                $deletes[$table] = $condition;

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

        self::assertCount(3, $deletes['prefix_authorization_rule']['resource_id IN (?)']);
        self::assertSame(['entity_type = ?' => 'language'], $deletes['prefix_ergonode_mapping_visibility']);
        self::assertSame([
            'prefix_ergonode_language_store_mapping',
            'prefix_ergonode_language',
        ], $droppedTables);
    }
}
