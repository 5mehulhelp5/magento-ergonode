<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Setup;

use Ergonode\ProductAttributeConsumer\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
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
    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRemovesOnlyProductAttributeConsumerRuntimeRecords(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'pac' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => $prefix . $table);

        try {
            $this->createRuntimeTables($connection, $prefix);
            $this->insertRuntimeRows($connection, $prefix);

            (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
            (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

            self::assertSame(
                ['ergonode_categories/cron/status'],
                $connection->fetchCol($connection->select()->from($prefix . 'core_config_data', ['path']))
            );
            self::assertSame(
                ['ergonode_category_synchronize'],
                $connection->fetchCol($connection->select()->from($prefix . 'cron_schedule', ['job_code']))
            );
            self::assertSame(
                ['categoryTreeStream'],
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'ergonode_import_cursor', ['process_code'])
                )
            );
            self::assertSame(
                ['attribute', 'category', 'language', 'option'],
                $connection->fetchCol(
                    $connection->select()
                        ->from($prefix . 'ergonode_mapping_visibility', ['entity_type'])
                        ->order('entity_type ASC')
                )
            );
            foreach (['magento_option_id', 'sync_status', 'sync_message'] as $column) {
                self::assertFalse($connection->tableColumnExists($prefix . 'ergonode_attribute_option', $column));
            }
            self::assertTrue($connection->tableColumnExists($prefix . 'ergonode_attribute_option', 'option_code'));
            self::assertSame(
                ['Ergonode_AttributeConsumer::attribute_mapping', 'Ergonode_AttributeConsumer::option_mapping'],
                $connection->fetchCol(
                    $connection->select()->from($prefix . 'authorization_rule', ['resource_id'])->order('resource_id')
                )
            );

            $connection->dropTable($prefix . 'core_config_data');
            $connection->dropTable($prefix . 'cron_schedule');
            $connection->dropTable($prefix . 'ergonode_import_cursor');
            (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));
        } finally {
            $temporaryTables = [
                'core_config_data',
                'cron_schedule',
                'ergonode_import_cursor',
                'ergonode_mapping_visibility',
                'ergonode_attribute_option',
                'authorization_rule',
            ];
            foreach ($temporaryTables as $table) {
                $tableName = $prefix . $table;
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        }
    }

    private function createRuntimeTables(AdapterInterface $connection, string $prefix): void
    {
        $connection->createTable(
            $connection->newTable($prefix . 'authorization_rule')
                ->addColumn('resource_id', Table::TYPE_TEXT, 255)
        );
        $connection->createTable(
            $connection->newTable($prefix . 'core_config_data')
                ->addColumn('path', Table::TYPE_TEXT, 255)
        );
        $connection->createTable(
            $connection->newTable($prefix . 'cron_schedule')
                ->addColumn('job_code', Table::TYPE_TEXT, 255)
        );
        $connection->createTable(
            $connection->newTable($prefix . 'ergonode_import_cursor')
                ->addColumn('process_code', Table::TYPE_TEXT, 64)
        );
        $connection->createTable(
            $connection->newTable($prefix . 'ergonode_mapping_visibility')
                ->addColumn('entity_type', Table::TYPE_TEXT, 64)
        );
        $connection->createTable(
            $connection->newTable($prefix . 'ergonode_attribute_option')
                ->addColumn('option_code', Table::TYPE_TEXT, 255)
                ->addColumn('magento_option_id', Table::TYPE_INTEGER)
                ->addColumn('sync_status', Table::TYPE_TEXT, 64)
                ->addColumn('sync_message', Table::TYPE_TEXT)
        );
    }

    private function insertRuntimeRows(AdapterInterface $connection, string $prefix): void
    {
        $connection->insertMultiple(
            $prefix . 'authorization_rule',
            [
            ['resource_id' => 'Ergonode_ProductAttributeConsumer::attribute_sync'],
            ['resource_id' => 'Ergonode_ProductAttributeConsumer::option_sync'],
            ['resource_id' => 'Ergonode_AttributeConsumer::attribute_mapping'],
            ['resource_id' => 'Ergonode_AttributeConsumer::option_mapping'],
            ]
        );
        $connection->insertMultiple(
            $prefix . 'core_config_data',
            [
            ['path' => 'ergonode_attributes/cron/status'],
            ['path' => 'ergonode_categories/cron/status'],
            ]
        );
        $connection->insertMultiple(
            $prefix . 'cron_schedule',
            [
            ['job_code' => 'ergonode_attribute_import'],
            ['job_code' => 'ergonode_category_synchronize'],
            ]
        );
        $connection->insertMultiple(
            $prefix . 'ergonode_import_cursor',
            [
            ['process_code' => 'attributeStream'],
            ['process_code' => 'categoryTreeStream'],
            ]
        );
        $connection->insertMultiple(
            $prefix . 'ergonode_mapping_visibility',
            [
            ['entity_type' => 'attribute'],
            ['entity_type' => 'option'],
            ['entity_type' => 'category'],
            ['entity_type' => 'language'],
            ]
        );
    }
}
