<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Integration\Model\Index;

use Ergonode\Media\Model\ResourceModel\ScanState;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class ScanStateIntegrationTest extends TestCase
{
    public function testEstimateAndCompletionMarkerSurviveFailuresAndLaterScans(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $prefix = 'tmp_media_scan_' . bin2hex(random_bytes(6));
        $stateTable = $prefix . '_state';
        $galleryTable = $prefix . '_gallery';
        $definition = $connection->newTable($stateTable)
            ->addColumn('state_id', Table::TYPE_SMALLINT, null, ['primary' => true, 'nullable' => false])
            ->addColumn('status', Table::TYPE_TEXT, 16, ['nullable' => false, 'default' => 'required']);
        foreach (['estimated_total', 'indexed', 'reused', 'removed', 'bytes'] as $name) {
            $definition->addColumn($name, Table::TYPE_BIGINT, null, ['nullable' => false, 'default' => 0]);
        }
        foreach (['started_at', 'updated_at', 'last_completed_at'] as $name) {
            $definition->addColumn($name, Table::TYPE_BIGINT);
        }
        $definition->addColumn('error', Table::TYPE_TEXT, '64k');
        $connection->createTable($definition);
        try {
            $connection->createTable($connection->newTable($galleryTable)
                ->addColumn('value', Table::TYPE_TEXT, 255)
                ->addColumn('media_type', Table::TYPE_TEXT, 32));
            $connection->insertMultiple($galleryTable, [
                ['value' => '/one.jpg', 'media_type' => 'image'],
                ['value' => '/one.jpg', 'media_type' => 'image'],
                ['value' => '/One.jpg', 'media_type' => 'image'],
                ['value' => 'video', 'media_type' => 'external-video'],
            ]);
            $resource = $this->createStub(ResourceConnection::class);
            $resource->method('getConnection')->willReturn($connection);
            $resource->method('getTableName')->willReturnCallback(
                static fn (string $name): string => $name === 'ergonode_media_scan' ? $stateTable : $galleryTable
            );
            $state = new ScanState($resource);
            self::assertSame(2, $state->estimate());
            self::assertSame('required', $state->read()['status']);
            $state->request($state->estimate());
            self::assertSame('pending', $state->read()['status']);
            $state->begin(2);
            $state->progress(['indexed' => 3, 'reused' => 0, 'removed' => 1], 100);
            $state->fail('Unreadable directory');
            self::assertNull($state->read()['last_completed_at']);
            self::assertSame(3, $state->read()['indexed']);
            self::assertSame('Unreadable directory', $state->read()['error']);
            $state->begin(2);
            $state->complete();
            $completed = $state->read()['last_completed_at'];
            self::assertGreaterThan(0, $completed);
            $state->request(2);
            self::assertSame($completed, $state->read()['last_completed_at']);
            self::assertNull($state->read()['started_at']);
            $state->begin(2);
            $state->fail('Later refresh failed');
            self::assertSame($completed, $state->read()['last_completed_at']);
            self::assertSame('failed', $state->read()['status']);
        } finally {
            $connection->dropTable($galleryTable);
            $connection->dropTable($stateTable);
        }
    }
}
