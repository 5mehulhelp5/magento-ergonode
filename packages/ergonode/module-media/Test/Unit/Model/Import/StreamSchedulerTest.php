<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Import;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\GraphQl\MultimediaClient;
use Ergonode\Media\Model\Import\StreamScheduler;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StreamSchedulerTest extends TestCase
{
    public function testCompetingRunCannotReadOrOverwriteTheCursorAndNextRunStartsAfterCommit(): void
    {
        $held = false;
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects(self::exactly(3))->method('lock')->with(StreamScheduler::LOCK_NAME, 0)
            ->willReturnCallback(static function () use (&$held): bool {
                if ($held) { return false; }
                $held = true;
                return true;
            });
        $locks->expects(self::exactly(2))->method('unlock')->with(StreamScheduler::LOCK_NAME)
            ->willReturnCallback(static function () use (&$held): bool { $held = false; return true; });
        $checkpoint = 'c0';
        $cursors = $this->createMock(CursorStorage::class);
        $cursors->expects(self::exactly(2))->method('get')->with(StreamScheduler::PROCESS_CODE)
            ->willReturnCallback(static function () use (&$checkpoint): array {
                return ['cursor' => $checkpoint];
            });
        $cursors->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (string $process, string $cursor) use (&$checkpoint): void { $checkpoint = $cursor; }
        );
        $database = $this->createMock(AdapterInterface::class);
        $database->expects(self::exactly(2))->method('beginTransaction');
        $database->expects(self::exactly(2))->method('commit');
        $database->expects(self::never())->method('rollBack');
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::exactly(2))->method('recordStream')->willReturn([23]);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::exactly(2))->method('dispatch');
        $client = $this->createMock(MultimediaClient::class);
        $contender = null;
        $seen = [];
        $client->expects(self::exactly(2))->method('stream')->willReturnCallback(
            static function (string $cursor) use (&$contender, &$seen): array {
                $seen[] = $cursor;
                if ($cursor === 'c0') {
                    self::assertSame(['pages' => 0, 'assets' => 0, 'products' => 0], $contender->schedule());
                }
                return ['items' => [['cursor' => $cursor . '-event']],
                    'cursor' => $cursor === 'c0' ? 'c1' : 'c2', 'has_more' => false];
            }
        );
        $first = $this->scheduler($client, $repository, $cursors, $publisher, $locks, $database);
        $contender = $this->scheduler($client, $repository, $cursors, $publisher, $locks, $database);
        self::assertSame(['pages' => 1, 'assets' => 1, 'products' => 1], $first->schedule());
        $contender->schedule();
        self::assertSame(['c0', 'c1'], $seen);
        self::assertSame('c2', $checkpoint);
        self::assertFalse($held);
    }

    #[DataProvider('failureStages')]
    public function testFailureRollsBackPageAndAlwaysReleasesTheStreamLock(string $stage): void
    {
        $events = [];
        $client = $this->createMock(MultimediaClient::class);
        $client->expects(self::once())->method('stream')->willReturn([
            'items' => [['path' => 'photo.jpg']], 'cursor' => 'next', 'has_more' => false,
        ]);
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::once())->method('recordStream')->willReturnCallback(
            static function () use ($stage, &$events): array {
                $events[] = 'page';
                if ($stage === 'page') { throw new RuntimeException('page failed'); }
                return [23];
            }
        );
        $cursors = $this->createMock(CursorStorage::class);
        $cursors->method('get')->willReturn(['cursor' => 'previous']);
        $cursors->expects($stage === 'cursor' ? self::once() : self::never())->method('save')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'cursor';
                throw new RuntimeException('cursor failed');
            });
        $database = $this->createMock(AdapterInterface::class);
        $database->expects(self::once())->method('beginTransaction')->willReturnCallback(
            static function () use (&$events): void { $events[] = 'begin'; }
        );
        $database->expects(self::never())->method('commit');
        $database->expects(self::once())->method('rollBack')->willReturnCallback(
            static function () use (&$events): void { $events[] = 'rollback'; }
        );
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock')->willReturnCallback(
            static function () use (&$events): bool { $events[] = 'unlock'; return true; }
        );
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        try {
            $this->scheduler($client, $repository, $cursors, $publisher, $locks, $database)->schedule();
            self::fail('The failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($stage . ' failed', $exception->getMessage());
        }
        self::assertSame($stage === 'page'
            ? ['begin', 'page', 'rollback', 'unlock']
            : ['begin', 'page', 'cursor', 'rollback', 'unlock'], $events);
    }

    public static function failureStages(): array
    {
        return ['asset persistence' => ['page'], 'checkpoint persistence' => ['cursor']];
    }

    public function testApiFailureReleasesLockWithoutStartingDatabaseTransaction(): void
    {
        $client = $this->createMock(MultimediaClient::class);
        $client->expects(self::once())->method('stream')->willThrowException(new RuntimeException('API failed'));
        $database = $this->createMock(AdapterInterface::class);
        $database->expects(self::never())->method('beginTransaction');
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock');
        $this->expectExceptionMessage('API failed');
        $this->scheduler($client, $this->createStub(MediaRepository::class), $this->createStub(CursorStorage::class),
            $this->createStub(QueuePublisher::class), $locks, $database)->schedule();
    }

    private function scheduler(
        MultimediaClient $client,
        MediaRepository $repository,
        CursorStorage $cursors,
        QueuePublisher $publisher,
        LockManagerInterface $locks,
        AdapterInterface $database
    ): StreamScheduler {
        $config = $this->createStub(MediaConfig::class);
        $config->method('getStreamPageSize')->willReturn(20);
        $connection = $this->createStub(ConfigProvider::class);
        $connection->method('isEnabled')->willReturn(true);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($database);
        return new StreamScheduler($client, $repository, $cursors, $publisher, $config, $connection, $locks, $resource);
    }
}
