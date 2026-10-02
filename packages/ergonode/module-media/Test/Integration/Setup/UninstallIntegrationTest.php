<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Integration\Setup;

use Ergonode\Media\Setup\Uninstall;
use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class UninstallIntegrationTest extends TestCase
{
    private const array RUNTIME_TABLES = [
        'queue_message' => ['id', 'topic_name'],
        'queue' => ['id', 'name'],
        'queue_message_status' => ['message_id', 'queue_id'],
        'cron_schedule' => ['job_code'],
        'core_config_data' => ['path'],
    ];

    private const array MODULE_TABLES = [
        'ergonode_media_scan',
        'ergonode_media_local_file',
        'ergonode_media_state',
        'ergonode_media_file_usage',
        'ergonode_media_product_work',
        'ergonode_media_product_usage',
        'ergonode_media_materialization',
        'ergonode_media_asset',
    ];

    public function testStandardUninstallIsResolvableByMagento(): void
    {
        self::assertInstanceOf(
            UninstallInterface::class,
            Bootstrap::getObjectManager()->create(Uninstall::class)
        );
    }

    public function testRuntimeCleanerDropsOnlyOwnedTablesAndPreservesOtherRecords(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'tmp_erg_media_uninstall_' . bin2hex(random_bytes(4)) . '_';
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(
            static fn(string $table): string => $prefix . $table
        );
        $tables = [
            ...array_keys(self::RUNTIME_TABLES),
            ...self::MODULE_TABLES,
        ];
        try {
            $this->createTables($connection, $prefix);
            $this->insertRecords($connection, $prefix);

            (new RuntimeRecordCleaner())->execute($setup);

            foreach (self::MODULE_TABLES as $table) {
                self::assertFalse($connection->isTableExists($prefix . $table), $table);
            }
            foreach (array_keys(self::RUNTIME_TABLES) as $table) {
                self::assertTrue($connection->isTableExists($prefix . $table), $table);
                self::assertSame(1, (int)$connection->fetchOne(
                    $connection->select()->from($prefix . $table, ['count' => 'COUNT(*)'])
                ));
            }
        } finally {
            foreach ($tables as $table) {
                if ($connection->isTableExists($prefix . $table)) {
                    $connection->dropTable($prefix . $table);
                }
            }
        }
    }

    private function createTables(AdapterInterface $connection, string $prefix): void
    {
        foreach (self::RUNTIME_TABLES as $table => $columns) {
            $definition = $connection->newTable($prefix . $table);
            foreach ($columns as $column) {
                $type = in_array($column, ['id', 'message_id', 'queue_id'], true)
                    ? Table::TYPE_INTEGER
                    : Table::TYPE_TEXT;
                $definition->addColumn($column, $type, $type === Table::TYPE_TEXT ? 255 : null);
            }
            $connection->createTable($definition);
        }
        foreach (self::MODULE_TABLES as $table) {
            $connection->createTable(
                $connection->newTable($prefix . $table)->addColumn('id', Table::TYPE_INTEGER)
            );
        }
    }

    private function insertRecords(AdapterInterface $connection, string $prefix): void
    {
        $connection->insert($prefix . 'queue_message', ['id' => 11, 'topic_name' => 'ergonode.media.gallery']);
        $connection->insert($prefix . 'queue', ['id' => 22, 'name' => 'ergonode.media.gallery']);
        $connection->insert($prefix . 'queue_message_status', ['message_id' => 11, 'queue_id' => 22]);
        $connection->insert($prefix . 'cron_schedule', ['job_code' => 'ergonode_media_recovery']);
        $connection->insert($prefix . 'core_config_data', ['path' => 'ergonode_products/media/gallery_mode']);
    }
}
