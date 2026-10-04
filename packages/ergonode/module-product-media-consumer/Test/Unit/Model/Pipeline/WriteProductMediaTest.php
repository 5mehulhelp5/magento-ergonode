<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Pipeline;

use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry, BatchScope};
use Ergonode\ProductMediaConsumer\Model\Pipeline\WriteProductMedia;
use Ergonode\ProductMediaConsumer\Plugin\InlineMediaDispatch;
use Ergonode\Media\Model\Queue\QueuePublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class WriteProductMediaTest extends TestCase
{
    public function testMediaFailureIsTerminalAndNextProductCompletesWithoutQueueDispatchOrUnrelatedClaim(): void
    {
        $entries = []; $intent = []; $work = [];
        foreach ([1, 2] as $id) {
            $entry = new BatchEntry(new ProductImportWorkItem($id, 'sku' . $id, 'sync', null, 'event', 'lease', 1));
            $entry->productId = $id; $entries[] = $entry; $work[$id] = new WorkItem($id, 'lease', 1);
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
            static fn(array $data): bool => $data['product_id'] === 1 && $data['stage'] === 'process:media'
        ));
        $context = new BatchContext($entries, $logger);
        foreach ([1, 2] as $id) { $context->media[$id] = function () use (&$intent, $id): void { $intent[] = $id; }; }
        $repo = $this->createMock(MediaRepositoryInterface::class);
        $repo->expects(self::never())->method('claim');
        $repo->expects(self::exactly(2))->method('claimProduct')->willReturnCallback(static fn(int $id): WorkItem => $work[$id]);
        $repo->expects(self::once())->method('fail')->with($work[1], 'gallery failed');
        $repo->expects(self::once())->method('complete')->with($work[2])->willReturn(true);
        $writer = $this->createMock(WorkProcessor::class);
        $writer->expects(self::exactly(2))->method('process')->willReturnCallback(static function (WorkItem $item): void {
            if ($item->productId === 1) { throw new RuntimeException('gallery failed'); }
        });
        $config = $this->createStub(MediaConfig::class); $config->method('getLeaseSeconds')->willReturn(300);
        $scan = $this->createStub(ScanReadiness::class); $scan->method('isBlocked')->willReturn(false);
        (new WriteProductMedia($repo, $writer, $config, $scan, new MaterializationCache()))->process($context);
        self::assertSame([1, 2], $intent);
        self::assertSame('gallery failed', $entries[0]->error->getMessage());
        self::assertNull($entries[1]->error);
    }

    public function testPipelineSuppressesMediaDispatchWhileIndependentImportsKeepIt(): void
    {
        $scope = new BatchScope(); $plugin = new InlineMediaDispatch($scope); $calls = 0;
        $publisher = $this->createStub(QueuePublisher::class);
        $proceed = function () use (&$calls): void { $calls++; };
        $plugin->aroundDispatch($publisher, $proceed);
        $scope->run(new BatchContext([], $this->createStub(LoggerInterface::class)), function () use ($plugin, $publisher, $proceed): void {
            $plugin->aroundDispatch($publisher, $proceed);
        });
        self::assertSame(1, $calls);
    }
}
