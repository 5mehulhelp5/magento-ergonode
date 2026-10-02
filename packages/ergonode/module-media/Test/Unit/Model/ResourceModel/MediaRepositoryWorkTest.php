<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MediaRepositoryWorkTest extends TestCase
{
    #[DataProvider('ownershipCases')]
    public function testChecksOwnershipUnderLockBeforeAnyProductWrite(array|false $row, bool $allowed): void
    {
        $events = [];
        [$repository, $connection] = $this->repository($row, $events);
        $connection->expects(self::once())->method('commit')->willReturnCallback(
            static function () use (&$events): void { $events[] = 'commit'; }
        );
        $connection->expects(self::never())->method('rollBack');
        $actual = $repository->applyWork(new WorkItem(23, 'current', 1), static function () use (&$events): void {
            $events[] = 'write';
        });
        self::assertSame($allowed, $actual);
        self::assertSame($allowed ? ['begin', 'lock', 'write', 'commit'] : ['begin', 'lock', 'commit'], $events);
    }

    public static function ownershipCases(): array
    {
        return [
            'current worker' => [['status' => 'processing', 'lease_token' => 'current', 'lease_expires_at' => '2026-10-02 12:10:00'], true],
            'reclaimed by another worker' => [['status' => 'processing', 'lease_token' => 'new', 'lease_expires_at' => '2026-10-02 12:10:00'], false],
            'rescheduled after stream update' => [['status' => 'pending', 'lease_token' => null, 'lease_expires_at' => null], false],
            'already completed by newer worker' => [false, false],
        ];
    }

    public function testExpiredLeaseThrowsSoConsumerRetriesInsteadOfDeletingWork(): void
    {
        $events = [];
        [$repository, $connection] = $this->repository([
            'status' => 'processing', 'lease_token' => 'current', 'lease_expires_at' => '2026-10-02 11:59:59',
        ], $events);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $this->expectException(LocalizedException::class);
        $repository->applyWork(new WorkItem(23, 'current', 1), static function (): void {
            self::fail('Expired worker must never write product data.');
        });
    }

    public function testProductWriteFailureRollsBackUsageAndProductTransaction(): void
    {
        $events = [];
        [$repository, $connection] = $this->repository([
            'status' => 'processing', 'lease_token' => 'current', 'lease_expires_at' => '2026-10-02 12:10:00',
        ], $events);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $this->expectExceptionMessage('write failed');
        $repository->applyWork(new WorkItem(23, 'current', 1), static function (): void {
            throw new RuntimeException('write failed');
        });
    }

    private function repository(array|false $row, array &$events): array
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction')->willReturnCallback(
            static function () use (&$events): void { $events[] = 'begin'; }
        );
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->expects(self::once())->method('forUpdate')->with(true)->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchRow')->with($select)->willReturnCallback(
            static function () use ($row, &$events): array|false { $events[] = 'lock'; return $row; }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtDate')->willReturn('2026-10-02 12:00:00');
        return [new MediaRepository($resource, $clock, $this->createStub(File::class)), $connection];
    }
}
