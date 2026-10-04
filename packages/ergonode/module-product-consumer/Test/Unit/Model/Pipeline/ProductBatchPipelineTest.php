<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry, BatchScope, FinishBatch, ProductBatchPipeline};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class ProductBatchPipelineTest extends TestCase
{
    private function stage(callable $action): BatchProcessorInterface
    {
        return new class($action) implements BatchProcessorInterface {
            public function __construct(private $action) {}
            public function process(BatchContext $context): void { ($this->action)($context); }
        };
    }

    private function entry(int $id): BatchEntry
    {
        $entry = new BatchEntry(new ProductImportWorkItem($id, 'sku-' . $id, 'sync', null, 'event', 'lease', 1));
        $entry->productId = $id;
        return $entry;
    }

    public function testAllThreePhasesCompleteBeforeFinalizationWithSharedContext(): void
    {
        $events = []; $scope = new BatchScope();
        $finish = $this->createMock(FinishBatch::class);
        $finish->expects(self::once())->method('process')->willReturnCallback(function (BatchContext $ctx) use (&$events): void {
            self::assertSame(['source', 'targets', 'attributes', 'media', 'extension'], $events);
            self::assertSame('prepared', $ctx->data['shared']);
            $events[] = 'cache';
        });
        $stage = function (string $name) use (&$events): BatchProcessorInterface {
            return $this->stage(function (BatchContext $ctx) use ($name, &$events): void {
                $ctx->data['shared'] = 'prepared'; $events[] = $name;
            });
        };
        $pipeline = new ProductBatchPipeline($scope, $finish, new NullLogger(),
            ['020_targets' => $stage('targets'), '010_source' => $stage('source')],
            ['200_media' => $stage('media'), '100_attributes' => $stage('attributes')],
            ['999_extension' => $stage('extension')]);
        $pipeline->run([$this->entry(1)]);
        self::assertSame('cache', end($events));
        self::assertNull($scope->get());
    }

    public function testFailedProductDoesNotRunDependentMediaButOtherProductsFinishAndErrorIsLoggedOnce(): void
    {
        $entries = [$this->entry(1), $this->entry(2)]; $media = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
            static fn(array $context): bool => $context['product_id'] === 1 && $context['stage'] === 'attributes'
                && $context['ergonode_sku'] === 'sku-1' && strlen($context['reference']) === 12
        ));
        $data = $this->stage(function (BatchContext $ctx): void {
            foreach ($ctx->entries as $entry) { $ctx->run($entry, 'attributes', static function () use ($entry): void {
                if ($entry->productId === 1) { throw new RuntimeException('wrong attribute type'); }
            }); }
        });
        $images = $this->stage(function (BatchContext $ctx) use (&$media): void {
            foreach ($ctx->entries as $entry) { $ctx->run($entry, 'media', function () use ($entry, &$media): void {
                $media[] = $entry->productId;
            }); }
        });
        $finish = $this->createMock(FinishBatch::class);
        $finish->expects(self::once())->method('process');
        (new ProductBatchPipeline(new BatchScope(), $finish, $logger, [], ['1' => $data, '2' => $images]))->run($entries);
        self::assertSame([2], $media);
        self::assertSame('wrong attribute type', $entries[0]->error->getMessage());
        self::assertNull($entries[1]->error);
    }

    public function testSuccessfulImportCheckpointWaitsForPostprocessorsAndFailedProductDoesNotRecordIt(): void
    {
        $scope = new BatchScope(); $events = [];
        $data = $this->stage(function (BatchContext $ctx) use ($scope, &$events): void {
            foreach ($ctx->entries as $entry) {
                $ctx->run($entry, 'data', function () use ($scope, $entry, &$events): void {
                    $scope->afterSuccess(function () use ($entry, &$events): void { $events[] = 'hash-' . $entry->productId; });
                });
            }
        });
        $media = $this->stage(function (BatchContext $ctx): void {
            $ctx->run($ctx->entries[0], 'media', static function (): void { throw new RuntimeException('download failed'); });
        });
        $post = $this->stage(function () use (&$events): void { $events[] = 'post'; });
        $finish = $this->createMock(FinishBatch::class);
        $finish->expects(self::once())->method('process')->willReturnCallback(function () use (&$events): void {
            self::assertSame(['post', 'hash-2'], $events); $events[] = 'cache';
        });
        (new ProductBatchPipeline($scope, $finish, new NullLogger(), [],
            ['100_data' => $data, '200_media' => $media], ['post' => $post]))->run([$this->entry(1), $this->entry(2)]);
        self::assertSame(['post', 'hash-2', 'cache'], $events);
    }

    public function testScopeAndPreloadedTargetsAreClearedAfterBatchFailure(): void
    {
        $scope = new BatchScope(); $cleared = false;
        $stage = $this->stage(function (BatchContext $ctx) use (&$cleared): void {
            $ctx->data['cleanup'][] = function () use (&$cleared): void { $cleared = true; };
            throw new RuntimeException('preload failed');
        });
        $finish = $this->createStub(FinishBatch::class);
        $context = (new ProductBatchPipeline($scope, $finish, new NullLogger(), ['pre' => $stage]))->run([$this->entry(1)]);
        self::assertTrue($cleared);
        self::assertNull($scope->get());
        self::assertSame('preprocess:pre', $context->entries[0]->failedStage);
    }

    public function testDeletedEventLoadsCurrentSourceWithoutUsingItsOldPayload(): void
    {
        $entry = new BatchEntry(new ProductImportWorkItem(1, 'sku-1',
            ProductImportWorkItem::OPERATION_DELETE, ['sku' => 'sku-1'], 'event', 'lease', 1));
        $loader = $this->createMock(\Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader::class);
        $loader->expects(self::once())->method('loadCurrent')->with('sku-1', null)->willReturn(null);
        $loader->expects(self::never())->method('load');
        $context = new BatchContext([$entry], new NullLogger());

        (new \Ergonode\ProductConsumer\Model\Pipeline\LoadSources($loader))->process($context);

        self::assertNull($entry->source);
        self::assertNull($entry->error);
    }
}
