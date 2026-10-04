<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Unit\Model\Cache;

use Ergonode\Product\Model\Cache\{ProductCacheFinalizer, ProductCacheInvalidator, ProductStateSnapshots};
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductCacheFinalizerTest extends TestCase
{
    public function testOnlyCurrentPassChangesIncludingNewProductsAreCompared(): void
    {
        $snapshots = $this->createStub(ProductStateSnapshots::class);
        $snapshots->method('hashes')->willReturnOnConsecutiveCalls(
            [42 => 'A', 84 => 'same'], [42 => 'B', 84 => 'same', 100 => 'created']
        );
        $cache = $this->createMock(ProductCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidate')->with([42, 100]);
        $finalizer = new ProductCacheFinalizer($snapshots, $cache);
        $before = $finalizer->begin([42, 84]);
        self::assertSame([42 => 'A', 84 => 'same'], $before);
        $changed = $finalizer->changes([42, 84, 100], $before);
        self::assertSame([42 => 'B', 100 => 'created'], $changed);
        $finalizer->complete($changed);
    }

    public function testInvalidationExceptionIsReportedWithoutAnotherCleanupAttempt(): void
    {
        $cache = $this->createMock(ProductCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidate')->with([1])->willThrowException(new RuntimeException('purge failed'));
        $finalizer = new ProductCacheFinalizer($this->createStub(ProductStateSnapshots::class), $cache);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('purge failed');
        $finalizer->complete([1 => 'new']);
    }

    public function testOnlyConcreteProductTagsAreInvalidatedAndNoopTouchesNoCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with(['cat_p_42', 'cat_p_84'])->willReturn(true);
        $events = $this->createMock(ManagerInterface::class);
        $events->expects(self::once())->method('dispatch')->with('clean_cache_by_tags', self::callback(
            static fn(array $data): bool => $data['object']->getIdentities() === ['cat_p_42', 'cat_p_84']
        ));
        $invalidator = new ProductCacheInvalidator($cache, $events);
        $invalidator->invalidate([]);
        $invalidator->invalidate([42, 84, 42, 0, -1]);
    }

    public function testFalseCleanupDoesNotCarryPendingStateIntoNextUnchangedPass(): void
    {
        $snapshots = $this->createStub(ProductStateSnapshots::class);
        $snapshots->method('hashes')->willReturnOnConsecutiveCalls(
            [42 => 'A'], [42 => 'B'], [42 => 'B'], [42 => 'B']
        );
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with(['cat_p_42'])->willReturn(false);
        $events = $this->createMock(ManagerInterface::class);
        $events->expects(self::never())->method('dispatch');
        $finalizer = new ProductCacheFinalizer($snapshots, new ProductCacheInvalidator($cache, $events));
        $before = $finalizer->begin([42]);

        $failure = null;
        try {
            $finalizer->complete($finalizer->changes([42], $before));
        } catch (RuntimeException $error) {
            $failure = $error;
        }
        self::assertInstanceOf(RuntimeException::class, $failure);
        self::assertStringContainsString('42', $failure->getMessage());
        self::assertStringContainsString('false', $failure->getMessage());
        // The next pass compares its own before/after values and does not remember the failure.
        $before = $finalizer->begin([42]);
        self::assertSame([42 => 'B'], $before);
        self::assertSame([], $finalizer->changes([42], $before));
        $finalizer->complete([]);
    }
}
