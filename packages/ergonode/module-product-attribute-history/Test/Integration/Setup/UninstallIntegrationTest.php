<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Integration\Setup;

use Ergonode\ProductAttributeHistory\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class UninstallIntegrationTest extends TestCase
{
    public function testRepeatedUninstallPreservesOtherPermissionsAndMappingData(): void
    {
        $objects = Bootstrap::getObjectManager();
        $connection = $objects->get(ResourceConnection::class)->getConnection();
        $prefix = 'pah' . bin2hex(random_bytes(4)) . '_';
        $tables = [
            'authorization_rule',
            'ergonode_product_attribute_history_operation',
            'ergonode_product_attribute_mapping',
        ];
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => $prefix . $table);

        try {
            $connection->createTable(
                $connection->newTable($prefix . 'authorization_rule')
                    ->addColumn('resource_id', Table::TYPE_TEXT, 255)
            );
            foreach (array_slice($tables, 1) as $table) {
                $connection->createTable(
                    $connection->newTable($prefix . $table)->addColumn('id', Table::TYPE_INTEGER)
                );
                $connection->insert($prefix . $table, ['id' => 1]);
            }
            $connection->insertMultiple($prefix . 'authorization_rule', [
                ['resource_id' => 'Ergonode_ProductAttributeHistory::view'],
                ['resource_id' => 'Ergonode_ProductAttributeHistory::view_extension'],
                ['resource_id' => 'Ergonode_ProductAttributeConsumer::attribute_sync'],
            ]);
            $uninstall = $objects->create(Uninstall::class);
            self::assertInstanceOf(UninstallInterface::class, $uninstall);
            $context = $this->createStub(ModuleContextInterface::class);

            $uninstall->uninstall($setup, $context);
            $uninstall->uninstall($setup, $context);

            self::assertFalse($connection->isTableExists($prefix . 'ergonode_product_attribute_history_operation'));
            self::assertSame(
                [
                    'Ergonode_ProductAttributeConsumer::attribute_sync',
                    'Ergonode_ProductAttributeHistory::view_extension',
                ],
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'authorization_rule', ['resource_id'])->order('resource_id')
                )
            );
            self::assertSame(
                [1],
                array_map('intval', $connection->fetchCol(
                    $connection->select()->from($prefix . 'ergonode_product_attribute_mapping', ['id'])
                ))
            );

            $connection->dropTable($prefix . 'authorization_rule');
            $uninstall->uninstall($setup, $context);
        } finally {
            foreach ($tables as $table) {
                if ($connection->isTableExists($prefix . $table)) {
                    $connection->dropTable($prefix . $table);
                }
            }
        }
    }
}
