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
        $repository->expects(self::never())->method('release');
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
}
