<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Integration\Setup;

use Ergonode\TemplateConsumer\Setup\Uninstall;
use Ergonode\TemplateConsumer\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class UninstallIntegrationTest extends TestCase
{
    private const array TABLE_COLUMNS = [
        'cron_schedule' => 'job_code',
        'core_config_data' => 'path',
        'ergonode_import_cursor' => 'process_code',
        'ergonode_mapping_visibility' => 'entity_type',
    ];

    private const array FOREIGN_VALUES = [
        'cron_schedule' => 'ergonode_category_synchronize',
        'core_config_data' => 'ergonode_categories/cron/status',
        'ergonode_import_cursor' => 'category_stream',
        'ergonode_mapping_visibility' => 'category',
    ];

    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRuntimeCleanerRemovesOnlyTemplateRecords(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'etc' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table): string => $prefix . $table);

        try {
            foreach (self::TABLE_COLUMNS as $table => $column) {
                $this->createTable($connection, $prefix . $table, $column);
            }
            $connection->insertMultiple($prefix . 'cron_schedule', [
                ['job_code' => 'ergonode_template_synchronize'],
                ['job_code' => 'ergonode_category_synchronize'],
            ]);
            $connection->insertMultiple($prefix . 'core_config_data', [
                ['path' => 'ergonode_templates/cron/status'],
                ['path' => 'ergonode_categories/cron/status'],
            ]);
            $connection->insertMultiple($prefix . 'ergonode_import_cursor', [
                ['process_code' => 'template_stream'],
                ['process_code' => 'templateList'],
                ['process_code' => 'category_stream'],
            ]);
            $connection->insertMultiple($prefix . 'ergonode_mapping_visibility', [
                ['entity_type' => 'template'],
                ['entity_type' => 'category'],
            ]);

            (new RuntimeRecordCleaner())->execute($setup);

            foreach (self::TABLE_COLUMNS as $table => $column) {
                self::assertSame(
                    [self::FOREIGN_VALUES[$table]],
                    $connection->fetchCol($connection->select()->from($prefix . $table, [$column])),
                    $table
                );
            }
        } finally {
            foreach (array_keys(self::TABLE_COLUMNS) as $table) {
                $tableName = $prefix . $table;
                if ($connection->isTableExists($tableName)) {
                    $connection->dropTable($tableName);
                }
            }
        }
    }

    private function createTable(AdapterInterface $connection, string $tableName, string $column): void
    {
        $connection->createTable(
            $connection->newTable($tableName)->addColumn($column, Table::TYPE_TEXT, 255)
        );
    }
}
