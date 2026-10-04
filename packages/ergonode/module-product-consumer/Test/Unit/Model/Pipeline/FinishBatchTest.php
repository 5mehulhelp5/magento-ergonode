<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\Product\Model\Cache\ProductCacheFinalizer;
use Ergonode\Product\Model\Cache\ProductCacheInvalidator;
use Ergonode\Product\Model\Cache\ProductStateSnapshots;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry, FinishBatch};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class FinishBatchTest extends TestCase
{
    #[DataProvider('cleanupFailures')]
    public function testCleanupFailureIsLoggedOnceWithoutFailingCompletedProduct(bool $throws): void
    {
        $entry = new BatchEntry(new ProductImportWorkItem(1, 'sku42', 'sync', null, 'e', 'l', 1));
        $entry->productId = 42;
        $logger = $this->createMock(LoggerInterface::class);
        $reason = $throws ? 'cache backend unavailable' : 'false';
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
            static fn(array $data): bool => $data['product_ids'] === [42]
                && $data['ergonode_skus'] === ['sku42']
                && $data['stage'] === 'postprocess:cache'
                && str_contains($data['exception']->getMessage(), $reason)
        ));
        $context = new BatchContext([$entry], $logger);
        $context->before = [42 => 'old'];
        $snapshots = $this->createStub(ProductStateSnapshots::class);
        $snapshots->method('hashes')->willReturn([42 => 'new']);
        $cache = $this->createMock(CacheInterface::class);
        $clean = $cache->expects(self::once())->method('clean')->with(['cat_p_42']);
        if ($throws) { $clean->willThrowException(new RuntimeException($reason)); }
        else { $clean->willReturn(false); }
        $events = $this->createMock(ManagerInterface::class);
        $events->expects(self::never())->method('dispatch');
        $finalizer = new ProductCacheFinalizer($snapshots, new ProductCacheInvalidator($cache, $events));
        $indexes = $this->createMock(ProductIndexInvalidator::class);
        $indexes->expects(self::once())->method('invalidate');

        (new FinishBatch($finalizer, $indexes))->process($context);

        self::assertNull($entry->error);
        self::assertNull($entry->failedStage);
        self::assertTrue($entry->changed);
    }

    public static function cleanupFailures(): array
    {
        return ['false result' => [false], 'exception' => [true]];
    }

    public function testFailureToReadProductChangesStillFailsTheBatch(): void
    {
        $entry = new BatchEntry(new ProductImportWorkItem(1, 'sku42', 'sync', null, 'e', 'l', 1));
        $entry->productId = 42;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $cache = $this->createMock(ProductCacheFinalizer::class);
        $cache->expects(self::once())->method('changes')->willThrowException(new RuntimeException('snapshot read failed'));
        $cache->expects(self::never())->method('complete');
        $indexes = $this->createMock(ProductIndexInvalidator::class);
        $indexes->expects(self::never())->method('invalidate');
        $context = new BatchContext([$entry], $logger);

        (new FinishBatch($cache, $indexes))->process($context);

        self::assertSame('postprocess:cache', $entry->failedStage);
        self::assertSame('snapshot read failed', $entry->error->getMessage());
    }

    public function testOnlyChangedCompletedProductsArePublishedIncludingMediaOnlyChanges(): void
    {
        $entries = [];
        foreach ([1, 2, 3] as $id) {
            $entry = new BatchEntry(new ProductImportWorkItem($id, 'sku' . $id, 'sync', null, 'e', 'l', 1));
            $entry->productId = $id; $entries[] = $entry;
        }
        $entries[1]->error = new RuntimeException('media failed');
        $context = new BatchContext($entries, new NullLogger());
        $context->before = [1 => 'old image', 2 => 'old data', 3 => 'unchanged'];
        $cache = $this->createMock(ProductCacheFinalizer::class);
        $cache->expects(self::once())->method('changes')->with([1, 2, 3], $context->before)->willReturn([1 => 'new image', 2 => 'partial data']);
        $cache->expects(self::once())->method('complete')->with([1 => 'new image']);
        $indexes = $this->createMock(ProductIndexInvalidator::class);
        $indexes->expects(self::once())->method('invalidate');
        (new FinishBatch($cache, $indexes))->process($context);
        self::assertTrue($entries[0]->changed);
        self::assertTrue($entries[1]->changed);
        self::assertFalse($entries[2]->changed);
    }
}
