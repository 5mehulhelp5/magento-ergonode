<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Integration\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 */
class CursorStorageTest extends TestCase
{
    public function testResetRetainsSuccessfulCheckpointAndRecordsResetTime(): void
    {
        $manager = Bootstrap::getObjectManager();
        $storage = $manager->get(CursorStorage::class);
        $resource = $manager->get(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('ergonode_import_cursor'), [
            'process_code' => 'monitor_test_stream',
            'cursor' => 'checkpoint',
            'synced_at' => '2026-01-01 10:00:00',
        ]);
        $storage->reset('monitor_test_stream');
        self::assertSame([
            'cursor' => null,
            'synced_at' => '2026-01-01 10:00:00',
        ], $storage->get('monitor_test_stream'));
        self::assertNotNull($storage->getResetAt('monitor_test_stream'));
        self::assertTrue($storage->acquireLease('monitor_test_stream', 'test-owner', 900));
        $storage->clearCursor('monitor_test_stream', 'test-owner');
        self::assertSame('2026-01-01 10:00:00', $storage->get('monitor_test_stream')['synced_at']);
        self::assertTrue($storage->completeLease('monitor_test_stream', 'test-owner', null));
        self::assertNotNull($storage->getResetAt('monitor_test_stream'));
    }
}
