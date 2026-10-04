<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Unit\Model\Cache;

use Ergonode\Product\Model\Cache\{ProductCacheFinalizer, ProductCacheInvalidator, ProductStateSnapshots};
use Ergonode\Product\Model\ResourceModel\ProductCacheState;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductCacheFinalizerTest extends TestCase
{
    private function state(): ProductCacheState
    {
        return new class extends ProductCacheState {
            public array $hashes = [];
            public function __construct() {}
            public function baseline(array $initial): array { $this->hashes += $initial; return array_intersect_key($this->hashes, $initial); }
            public function save(array $hashes): void { $this->hashes = array_replace($this->hashes, $hashes); }
        };
    }

    public function testFailedPassRetainsOriginalBaselineAndOnlyExplicitNextPassInvalidates(): void
    {
        $state = $this->state();
        $snapshots = $this->createStub(ProductStateSnapshots::class);
        $snapshots->method('hashes')->willReturnOnConsecutiveCalls([42 => 'A'], [42 => 'B'], [42 => 'B'], [42 => 'B'], [42 => 'B'], [42 => 'B']);
        $cache = $this->createMock(ProductCacheInvalidator::class);
        $cache->expects(self::exactly(2))->method('invalidate')->willReturnCallback(static function (array $ids): void {
            self::assertTrue($ids === [42] || $ids === []);
        });
        $finalizer = new ProductCacheFinalizer($snapshots, $state, $cache);
        $before = $finalizer->begin([42]);
        $changed = $finalizer->changes([42], $before);
        self::assertSame([42 => 'B'], $changed);
        // Failed media: do not complete, no automatic invocation is registered.
        self::assertSame([42 => 'A'], $state->hashes);
        $before = $finalizer->begin([42]);
        self::assertSame([42 => 'A'], $before);
        $finalizer->complete($finalizer->changes([42], $before));
        self::assertSame([42 => 'B'], $state->hashes);
        $before = $finalizer->begin([42]);
        self::assertSame([], $finalizer->changes([42], $before));
        $finalizer->complete([]);
    }

    public function testInvalidationFailureDoesNotAdvanceCheckpoint(): void
    {
        $state = $this->state(); $state->hashes = [1 => 'old'];
        $cache = $this->createMock(ProductCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidate')->willThrowException(new RuntimeException('purge failed'));
        $finalizer = new ProductCacheFinalizer($this->createStub(ProductStateSnapshots::class), $state, $cache);
        try { $finalizer->complete([1 => 'new']); self::fail('Expected failure'); }
        catch (RuntimeException $error) { self::assertSame('purge failed', $error->getMessage()); }
        self::assertSame([1 => 'old'], $state->hashes);
    }

    public function testOnlyConcreteProductTagsAreInvalidatedAndNoopTouchesNoCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with(['cat_p_42', 'cat_p_84']);
        $events = $this->createMock(ManagerInterface::class);
        $events->expects(self::once())->method('dispatch')->with('clean_cache_by_tags', self::callback(
            static fn(array $data): bool => $data['object']->getIdentities() === ['cat_p_42', 'cat_p_84']
        ));
        $invalidator = new ProductCacheInvalidator($cache, $events);
        $invalidator->invalidate([]);
        $invalidator->invalidate([42, 84, 42, 0, -1]);
    }
}
