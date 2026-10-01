<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Integration\Setup;

use Ergonode\ProductAttribute\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class UninstallIntegrationTest extends TestCase
{
    public function testRepeatedUninstallPreservesSharedIdentityAndExtensionConfiguration(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'pa' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => $prefix . $table);
        $mappingTables = ['ergonode_product_attribute_mapping', 'ergonode_product_option_mapping'];

        try {
            $connection->createTable(
                $connection->newTable($prefix . 'core_config_data')->addColumn('path', Table::TYPE_TEXT, 255)
            );
            foreach ($mappingTables as $table) {
                $connection->createTable(
                    $connection->newTable($prefix . $table)->addColumn('id', Table::TYPE_INTEGER)
                );
            }
            $connection->insertMultiple($prefix . 'core_config_data', [
                ['path' => 'ergonode_products/attributes/sku_mode'],
                ['path' => 'ergonode_products/attributes/price_default'],
                ['path' => 'ergonode_products/identity/sku_mode'],
                ['path' => 'ergonode_products/publication/category_mode'],
            ]);
            $uninstall = Bootstrap::getObjectManager()->create(Uninstall::class);
            $context = $this->createStub(ModuleContextInterface::class);

            $uninstall->uninstall($setup, $context);
            $uninstall->uninstall($setup, $context);

            foreach ($mappingTables as $table) {
                self::assertFalse($connection->isTableExists($prefix . $table));
            }
            self::assertSame(
                ['ergonode_products/identity/sku_mode', 'ergonode_products/publication/category_mode'],
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'core_config_data', ['path'])->order('path')
                )
            );

            $connection->dropTable($prefix . 'core_config_data');
            $uninstall->uninstall($setup, $context);
        } finally {
            foreach (['core_config_data', ...$mappingTables] as $table) {
                if ($connection->isTableExists($prefix . $table)) {
                    $connection->dropTable($prefix . $table);
                }
            }
        }
    }
}
