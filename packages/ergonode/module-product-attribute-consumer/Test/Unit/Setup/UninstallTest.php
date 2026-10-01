<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Setup;

use Ergonode\ProductAttributeConsumer\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesRuntimeRecordsAndLegacyCacheColumnsWithoutTouchingMagentoEav(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $deleted = [];
        $connection->expects(self::exactly(4))->method('delete')
            ->willReturnCallback(
                static function (string $table, array $where) use (&$deleted): int {
                    $deleted[] = [$table, $where];

                    return 1;
                }
            );
        $connection->expects(self::exactly(5))->method('isTableExists')->willReturn(true);
        $connection->expects(self::exactly(3))->method('tableColumnExists')->willReturn(true);
        $dropped = [];
        $connection->expects(self::exactly(3))->method('dropColumn')
            ->willReturnCallback(
                static function (string $table, string $column) use (&$dropped): bool {
                    $dropped[] = [$table, $column];

                    return true;
                }
            );
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn(string $table): string => 'prefix_' . $table
        );

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(
            [
            ['prefix_core_config_data', ['path IN (?)' => [
                'ergonode_attributes/mapping/map_identical_codes',
                'ergonode_attributes/options/synchronize_sort_order',
                'ergonode_attributes/options/delete_missing_magento_options',
                'ergonode_attributes/cron/status',
                'ergonode_attributes/cron/schedule',
            ]]],
            ['prefix_cron_schedule', ['job_code = ?' => 'ergonode_attribute_import']],
            ['prefix_ergonode_import_cursor', ['process_code = ?' => 'attributeStream']],
            ['prefix_authorization_rule', ['resource_id IN (?)' => [
                'Ergonode_ProductAttributeConsumer::attribute_sync',
                'Ergonode_ProductAttributeConsumer::option_sync',
            ]]],
            ],
            $deleted
        );
        self::assertSame(
            [
            ['prefix_ergonode_attribute_option', 'magento_option_id'],
            ['prefix_ergonode_attribute_option', 'sync_status'],
            ['prefix_ergonode_attribute_option', 'sync_message'],
            ],
            $dropped
        );
    }

    public function testToleratesMissingTables(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('tableColumnExists');
        $connection->expects(self::never())->method('dropColumn');
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
    }
}
