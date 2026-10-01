<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Zend_Db_Expr;

class CursorStorageTest extends TestCase
{
    public function testReturnsCursorAndSynchronizationTime(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('ergonode_import_cursor', ['cursor', 'synced_at'])
            ->willReturnSelf();
        $select->expects(self::once())
            ->method('where')
            ->with('process_code = ?', 'template_stream')
            ->willReturnSelf();
        $select->expects(self::once())->method('limit')->with(1)->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn([
                'cursor' => 'after',
                'synced_at' => '2026-08-27 10:05:00',
                'process_code' => 'template_stream',
                'started_at' => '2026-08-27 10:00:00',
            ]);

        self::assertSame(
            ['cursor' => 'after', 'synced_at' => '2026-08-27 10:05:00'],
            $this->storage($connection)->get('template_stream')
        );
    }

    public function testAcquiresOnlyAnAvailableOrExpiredLease(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'ergonode_import_cursor',
                [
                    'process_code' => 'template_stream',
                    'cursor' => null,
                ],
                ['process_code']
            );
        $connection->expects(self::once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $where): int {
                self::assertSame('ergonode_import_cursor', $table);
                self::assertSame('operation-token', $data['lock_token']);
                self::assertInstanceOf(Zend_Db_Expr::class, $data['started_at']);
                self::assertNull($data['completed_at']);
                self::assertSame('template_stream', $where['process_code = ?']);
                self::assertArrayHasKey(
                    '(lock_token IS NULL OR started_at IS NULL OR started_at <= ?)',
                    $where
                );

                return 1;
            });

        self::assertTrue(
            $this->storage($connection)->acquireLease('template_stream', 'operation-token', 900)
        );
    }

    public function testCompletesOnlyTheOwnedLeaseAndStoresCursor(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $where): int {
                self::assertSame('ergonode_import_cursor', $table);
                self::assertSame('after', $data['cursor']);
                self::assertNull($data['lock_token']);
                self::assertInstanceOf(Zend_Db_Expr::class, $data['completed_at']);
                self::assertSame('template_stream', $where['process_code = ?']);
                self::assertSame('operation-token', $where['lock_token = ?']);

                return 1;
            });

        self::assertTrue(
            $this->storage($connection)->completeLease('template_stream', 'operation-token', 'after')
        );
    }

    public function testResetPreservesPreviousCheckpointTimeAndLease(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::once())->method('insertOnDuplicate')->with(
            'ergonode_import_cursor',
            self::callback(static fn (array $data): bool => $data['cursor'] === null
                && $data['reset_at'] instanceof Zend_Db_Expr && !isset($data['synced_at'])),
            ['cursor', 'reset_at']
        );
        $this->storage($connection)->reset('attributeStream');
    }

    private function storage(AdapterInterface $connection): CursorStorage
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return new CursorStorage($resourceConnection);
    }
}
