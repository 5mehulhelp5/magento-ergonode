<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Integration\Setup;

use Ergonode\ProductPublisher\Setup\Uninstall;
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
    public function testRepeatedUninstallPreservesOtherModulesConfigurationAndAuthorizationRules(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'pp' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => $prefix . $table);

        try {
            $connection->createTable(
                $connection->newTable($prefix . 'core_config_data')->addColumn('path', Table::TYPE_TEXT, 255)
            );
            $connection->createTable(
                $connection->newTable($prefix . 'authorization_rule')->addColumn('resource_id', Table::TYPE_TEXT, 255)
            );
            $connection->createTable(
                $connection->newTable($prefix . 'ergonode_product_publication_result')
                    ->addColumn('product_id', Table::TYPE_INTEGER)
            );
            $paths = ['ergonode_products/identity/sku_mode', 'ergonode_products/publication/category_mode'];
            $connection->insertMultiple($prefix . 'core_config_data', array_map(
                static fn(string $path): array => ['path' => $path],
                $paths
            ));
            $connection->insertMultiple($prefix . 'authorization_rule', [
                ['resource_id' => 'Ergonode_ProductPublisher::publish'],
                ['resource_id' => 'Ergonode_Product::products'],
            ]);
            $uninstall = Bootstrap::getObjectManager()->create(Uninstall::class);
            $context = $this->createStub(ModuleContextInterface::class);

            $uninstall->uninstall($setup, $context);
            $uninstall->uninstall($setup, $context);

            self::assertFalse($connection->isTableExists($prefix . 'ergonode_product_publication_result'));
            self::assertSame(
                $paths,
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'core_config_data', ['path'])->order('path')
                )
            );
            self::assertSame(
                ['Ergonode_Product::products'],
                $connection->fetchCol($connection->select()->from($prefix . 'authorization_rule', ['resource_id']))
            );
        } finally {
            foreach (['core_config_data', 'authorization_rule', 'ergonode_product_publication_result'] as $table) {
                if ($connection->isTableExists($prefix . $table)) {
                    $connection->dropTable($prefix . $table);
                }
            }
        }
    }
}
