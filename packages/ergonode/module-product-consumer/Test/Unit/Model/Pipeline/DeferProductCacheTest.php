<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry, BatchScope};
use Ergonode\ProductConsumer\Plugin\DeferProductCache;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Manager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DeferProductCacheTest extends TestCase
{
    public function testNativeCacheSignalsWaitOnlyForProductsInTheBatch(): void
    {
        $scope = new BatchScope(); $plugin = new DeferProductCache($scope); $calls = [];
        $product = $this->createStub(Product::class); $product->method('getId')->willReturn(42);
        $other = $this->createStub(Product::class); $other->method('getId')->willReturn(84);
        $events = $this->createStub(Manager::class);
        $entry = new BatchEntry(new ProductImportWorkItem(1, 'SKU', 'sync', null, 'e', 'l', 1));
        $entry->productId = 42;
        $proceed = function (...$args) use (&$calls): string { $calls[] = $args; return 'executed'; };
        $scope->run(new BatchContext([$entry], new NullLogger()), function () use ($plugin, $product, $other, $events, $proceed): void {
            self::assertSame($product, $plugin->aroundCleanModelCache($product, $proceed));
            self::assertNull($plugin->aroundDispatch($events, $proceed, 'clean_cache_by_tags', ['object' => $product]));
            self::assertSame('executed', $plugin->aroundCleanModelCache($other, $proceed));
            self::assertSame('executed', $plugin->aroundDispatch($events, $proceed, 'catalog_product_save_after', ['object' => $product]));
        });
        self::assertSame('executed', $plugin->aroundCleanModelCache($product, $proceed));
        self::assertCount(3, $calls);
    }
}
