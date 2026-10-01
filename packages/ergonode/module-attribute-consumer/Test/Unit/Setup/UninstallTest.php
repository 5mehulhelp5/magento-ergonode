<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Setup;

use Ergonode\AttributeConsumer\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOwnedTablesAndAclRules(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects(self::exactly(3))->method('delete')->willReturnCallback(
            static function (string $table, array $condition): int {
                if ($table === 'prefix_authorization_rule') {
                    self::assertSame([
                        'Ergonode_AttributeConsumer::attribute_refresh',
                        'Ergonode_AttributeConsumer::option_refresh',
                    ], $condition['resource_id IN (?)']);
                } elseif ($table === 'prefix_flag') {
                    self::assertSame(['flag_code = ?' => 'ergonode_attribute_definition_check'], $condition);
                } else {
                    self::assertSame('prefix_ergonode_import_cursor', $table);
                    self::assertSame(['process_code = ?' => 'attribute_definition_snapshot'], $condition);
                }

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

        self::assertSame([
            'prefix_ergonode_attribute_option',
            'prefix_ergonode_attribute',
        ], $droppedTables);
    }
}
