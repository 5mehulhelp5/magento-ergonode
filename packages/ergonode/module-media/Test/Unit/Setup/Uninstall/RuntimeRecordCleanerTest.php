<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup\Uninstall;

use Ergonode\Media\Setup\Uninstall\RuntimeRecordCleaner;
use Ergonode\Media\Model\Config\MediaConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class RuntimeRecordCleanerTest extends TestCase
{
    public function testRemovesRuntimeRecordsAndAllModuleTables(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturn(true);
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->expects(self::exactly(2))->method('select')->willReturn($select);
        $connection->expects(self::exactly(2))->method('fetchCol')->willReturnOnConsecutiveCalls([11], [22]);

        $deletes = [
            ['prefix_queue_message_status', ['message_id IN (?)' => [11]]],
            ['prefix_queue_message_status', ['queue_id IN (?)' => [22]]],
            ['prefix_queue_message', ['topic_name = ?' => 'ergonode.media.gallery']],
            ['prefix_queue', ['name = ?' => 'ergonode.media.gallery']],
            ['prefix_cron_schedule', [
                'job_code IN (?)' => [
                    'ergonode_multimedia_stream_schedule', 'ergonode_media_recovery', 'ergonode_media_scan',
                ],
            ]],
            ['prefix_core_config_data', ['path IN (?)' => [
                MediaConfig::XML_PATH_GALLERY_MODE, MediaConfig::XML_PATH_STREAM_PAGE_SIZE,
                MediaConfig::XML_PATH_BATCH_SIZE, MediaConfig::XML_PATH_MAXIMUM_ATTEMPTS,
                MediaConfig::XML_PATH_LEASE_SECONDS,
            ]]],
        ];
        $connection->expects(self::exactly(count($deletes)))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $where) use (&$deletes): int {
                self::assertSame(array_shift($deletes), [$table, $where]);

                return 1;
            });
        $tables = [
            'prefix_ergonode_media_scan',
            'prefix_ergonode_media_local_file',
            'prefix_ergonode_media_state',
            'prefix_ergonode_media_file_usage',
            'prefix_ergonode_media_product_work',
            'prefix_ergonode_media_product_usage',
            'prefix_ergonode_media_materialization',
            'prefix_ergonode_media_asset',
        ];
        $connection->expects(self::exactly(count($tables)))
            ->method('dropTable')
            ->willReturnCallback(static function (string $table) use (&$tables): bool {
                self::assertSame(array_shift($tables), $table);

                return true;
            });

        (new RuntimeRecordCleaner())->execute($setup);

        self::assertSame([], $deletes);
        self::assertSame([], $tables);
    }

    public function testCleansAvailableQueueTablesAfterPartialSchemaFailure(): void
    {
        $setup = $this->createStub(SchemaSetupInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn(string $table): string => 'prefix_' . $table);
        $connection->method('isTableExists')->willReturnCallback(
            static fn(string $table): bool => $table === 'prefix_queue_message'
        );
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchCol')->willReturn([11]);
        $connection->expects(self::once())->method('delete')->with(
            'prefix_queue_message',
            ['topic_name = ?' => 'ergonode.media.gallery']
        );
        $connection->expects(self::never())->method('dropTable');

        (new RuntimeRecordCleaner())->execute($setup);
    }
}
