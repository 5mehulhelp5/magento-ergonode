<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Queue;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\Queue\Consumer;
use Ergonode\Media\Model\Queue\QueuePublisher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConsumerScanGateTest extends TestCase
{
    #[DataProvider('readiness')]
    public function testBlockedJobsRemainUnclaimedUntilTheScanCompletes(bool $blocked): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $item = new WorkItem(41, 'lease', 1);
        $repository->expects($blocked ? self::never() : self::once())->method('claim')->willReturn([$item]);
        $repository->expects($blocked ? self::never() : self::once())->method('complete')->with($item);
        $repository->expects(self::never())->method('fail');
        $processor = $this->createMock(WorkProcessor::class);
        $processor->expects($blocked ? self::never() : self::once())->method('process')->with($item);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $connection = $this->createStub(ConfigProvider::class);
        $connection->method('isEnabled')->willReturn(true);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn($blocked);
        (new Consumer(
            $repository,
            $processor,
            $publisher,
            $this->createStub(MediaConfig::class),
            $connection,
            $this->createStub(LoggerInterface::class),
            $readiness,
            new MaterializationCache()
        ))->process('drain');
    }

    public static function readiness(): array
    {
        return [[true], [false]];
    }

    public function testFailureIsLoggedOnceAndDoesNotStopTheNextProduct(): void
    {
        $failed = new WorkItem(41, 'first-lease', 1, true);
        $next = new WorkItem(42, 'second-lease', 1, true);
        $error = new \RuntimeException(
            'Unable to store Ergonode media "file.png" (asset 7, revision 2) at stage "write temporary file": Expected 5 bytes, wrote 2.'
        );
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('claim')->willReturn([$failed, $next]);
        $repository->expects(self::once())->method('fail')->with($failed, $error->getMessage());
        $repository->expects(self::once())->method('complete')->with($next);
        $processor = $this->createMock(WorkProcessor::class);
        $processor->expects(self::exactly(2))->method('process')->willReturnCallback(
            static function (WorkItem $item) use ($failed, $error): void {
                if ($item === $failed) {
                    throw $error;
                }
            }
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Unable to synchronize Ergonode media.',
            ['product_id' => 41, 'exception' => $error]
        );
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $config = $this->createStub(MediaConfig::class);
        $connection = $this->createStub(ConfigProvider::class);
        $connection->method('isEnabled')->willReturn(true);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn(false);

        (new Consumer($repository, $processor, $publisher, $config, $connection, $logger,
            $readiness, new MaterializationCache()))->process('drain');
    }

    public function testCachePreparationFailureIsLoggedAndEndsClaimedWorkWithoutWritingMedia(): void
    {
        $item = new WorkItem(41, 'lease', 1, true);
        $error = new \RuntimeException('Cache state storage unavailable.');
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('claim')->willReturn([$item]);
        $repository->expects(self::once())->method('fail')->with($item, $error->getMessage());
        $repository->expects(self::never())->method('complete');
        $processor = $this->createMock(WorkProcessor::class);
        $processor->expects(self::never())->method('process');
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $cache = $this->createMock(\Ergonode\Product\Model\Cache\ProductCacheFinalizer::class);
        $cache->expects(self::once())->method('begin')->with([41])->willThrowException($error);
        $cache->expects(self::never())->method('complete');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Unable to prepare product cache state before Ergonode media synchronization.',
            ['product_ids' => [41], 'stage' => 'preprocess:cache', 'exception' => $error]
        );
        $connection = $this->createStub(ConfigProvider::class);
        $connection->method('isEnabled')->willReturn(true);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn(false);

        (new Consumer($repository, $processor, $publisher, $this->createStub(MediaConfig::class),
            $connection, $logger, $readiness, new MaterializationCache(), $cache))->process('drain');
    }

    public function testStandaloneMediaRefreshesOnlyCompletedProductsAfterTheirWrites(): void
    {
        $first = new WorkItem(41, 'first', 1, true);
        $second = new WorkItem(42, 'second', 1, true);
        $events = [];
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('claim')->willReturn([$first, $second]);
        $repository->expects(self::exactly(2))->method('complete')->willReturnCallback(
            static function (WorkItem $item) use (&$events): bool {
                $events[] = 'complete:' . $item->productId;
                return true;
            }
        );
        $repository->expects(self::never())->method('fail');
        $processor = $this->createMock(WorkProcessor::class);
        $processor->expects(self::exactly(2))->method('process')->willReturnCallback(
            static function (WorkItem $item) use (&$events): void {
                $events[] = 'write:' . $item->productId;
            }
        );
        $cache = $this->createMock(\Ergonode\Product\Model\Cache\ProductCacheFinalizer::class);
        $cache->expects(self::once())->method('begin')->with([41, 42])->willReturn([41 => 'old', 42 => 'same']);
        $cache->expects(self::once())->method('changes')->with([41, 42], [41 => 'old', 42 => 'same'])
            ->willReturn([41 => 'new']);
        $cache->expects(self::once())->method('complete')->with([41 => 'new'])->willReturnCallback(
            static function () use (&$events): void { $events[] = 'cache'; }
        );
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        $connection = $this->createStub(ConfigProvider::class);
        $connection->method('isEnabled')->willReturn(true);
        $readiness = $this->createStub(ScanReadiness::class);
        $readiness->method('isBlocked')->willReturn(false);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        (new Consumer($repository, $processor, $publisher, $this->createStub(MediaConfig::class),
            $connection, $logger, $readiness, new MaterializationCache(), $cache))->process('drain');

        self::assertSame(['write:41', 'complete:41', 'write:42', 'complete:42', 'cache'], $events);
    }
}
