<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Setup;

use Ergonode\ProductAttribute\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testRemovesOnlyProductMappingDataAndConfiguration(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $deleted = [];
        $connection->expects(self::exactly(3))->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deleted): int {
                $deleted[] = [$table, $where];

                return 1;
            }
        );
        $connection->expects(self::exactly(5))->method('isTableExists')->willReturn(true);
        $dropped = [];
        $connection->expects(self::exactly(2))->method('dropTable')
            ->willReturnCallback(
                static function (string $table) use (&$dropped): bool {
                    $dropped[] = $table;

                    return true;
                }
            );
        $setup = $this->createSetup($connection);

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame(
            [
            ['prefix_authorization_rule', ['resource_id IN (?)' => [
                'Ergonode_AttributeConsumer::attribute_mapping',
                'Ergonode_AttributeConsumer::attribute_save',
                'Ergonode_AttributeConsumer::option_mapping',
                'Ergonode_AttributeConsumer::option_save',
            ]]],
            ['prefix_core_config_data',
            ['path IN (?)' => [
                'ergonode_products/attributes/assigned_sku_capability_verified',
                'ergonode_products/attributes/sku_mode',
                'ergonode_products/attributes/url_key',
                'ergonode_products/attributes/price_mode',
                'ergonode_products/attributes/price_default',
                'ergonode_products/attributes/status',
                'ergonode_products/attributes/status_default',
                'ergonode_products/attributes/visibility',
                'ergonode_products/attributes/visibility_default',
            ]]],
            ['prefix_ergonode_mapping_visibility', ['entity_type IN (?)' => ['attribute', 'option']]],
            ],
            $deleted
        );

        self::assertSame(
            [
            'prefix_ergonode_product_option_mapping',
            'prefix_ergonode_product_attribute_mapping',
            ],
            $dropped
        );
        self::assertNotContains('prefix_eav_attribute', $dropped);
        self::assertNotContains('prefix_eav_attribute_option', $dropped);
    }

    public function testToleratesMissingTables(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('dropTable');

        (new Uninstall())->uninstall($this->createSetup($connection), $this->createStub(ModuleContextInterface::class));
    }

    private function createSetup(AdapterInterface $connection): SchemaSetupInterface
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn(string $table): string => 'prefix_' . $table
        );

        return $setup;
    }
}
