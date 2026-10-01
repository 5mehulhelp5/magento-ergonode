<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheIdentity;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use ReflectionProperty;

class CategoryCacheInvalidatorTest extends TestCase
{
    public function testInvalidatesCategoryTagsAndFrontendCaches(): void
    {
        $tags = [
            Category::CACHE_TAG,
            Category::CACHE_TAG . '_10',
            Category::CACHE_TAG . '_11',
        ];
        $cleanedTypes = [];

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with($tags);
        $types = $this->createMock(TypeListInterface::class);
        $types->expects($this->exactly(2))->method('cleanType')
            ->willReturnCallback(static function (string $type) use (&$cleanedTypes): void {
                $cleanedTypes[] = $type;
            });
        $events = $this->createMock(ManagerInterface::class);
        $events->expects($this->once())->method('dispatch')->with(
            'clean_cache_by_tags',
            self::callback(static function (array $payload) use ($tags): bool {
                $identity = $payload['object'] ?? null;

                return $identity instanceof CategoryCacheIdentity
                    && $identity->getIdentities() === $tags;
            })
        );

        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects(self::once())->method('clearCache');
        (new CategoryCacheInvalidator($cache, $types, $events, $provider))->invalidateCategories([10, 11, 10, 0]);

        self::assertSame(['block_html', 'full_page'], $cleanedTypes);
    }

    public function testSkipsCacheCleanWhenNoCategoryIdsWereSynced(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('clean');
        $types = $this->createMock(TypeListInterface::class);
        $types->expects($this->never())->method('cleanType');
        $events = $this->createMock(ManagerInterface::class);
        $events->expects($this->never())->method('dispatch');

        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects(self::never())->method('clearCache');
        (new CategoryCacheInvalidator($cache, $types, $events, $provider))->invalidateCategories([]);
    }

    public function testNestedBatchFlushesOnceAfterPartialFailureAndResetsPendingState(): void
    {
        $inside = true;
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->willReturnCallback(
            static function (array $tags) use (&$inside): bool {
                self::assertFalse($inside);
                self::assertSame(['cat_c', 'cat_c_10', 'cat_c_11'], $tags);
                return true;
            }
        );
        $types = $this->createMock(TypeListInterface::class);
        $types->expects(self::exactly(2))->method('cleanType');
        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects(self::once())->method('clearCache')->willReturnCallback(
            static function () use (&$inside): void {
                self::assertFalse($inside);
            }
        );
        $invalidator = new CategoryCacheInvalidator(
            $cache,
            $types,
            $this->createStub(ManagerInterface::class),
            $provider
        );
        try {
            $invalidator->defer(function () use ($invalidator, &$inside): void {
                $invalidator->invalidateCategories([10]);
                $invalidator->defer(static fn () => $invalidator->invalidateCategories([10, 11]));
                $inside = false;
                throw new RuntimeException('partial write');
            });
            self::fail('Expected original failure.');
        } catch (RuntimeException $error) {
            self::assertSame('partial write', $error->getMessage());
        }
        $invalidator->defer(static fn (): int => 1);
    }

    public function testBackfillKeepsWorkingIndexUntilPassBoundary(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $provider = new MagentoCategoryProvider($factory);
        $cacheProperty = new ReflectionProperty($provider, 'categoriesCache');
        $category = ['id' => 10, 'parent_id' => 2, 'label' => 'Before'];
        $cacheProperty->setValue($provider, [2 => [10 => $category]]);
        $invalidator = new CategoryCacheInvalidator(
            $this->createStub(CacheInterface::class),
            $this->createStub(TypeListInterface::class),
            $this->createStub(ManagerInterface::class),
            $provider
        );
        $invalidator->defer(static function () use ($invalidator, $provider, $cacheProperty, $category): void {
            for ($index = 0; $index < 100; $index++) {
                $invalidator->invalidateCategories([10]);
                self::assertSame($category, $provider->getCategory(10, 2));
            }
            $invalidator->refreshCategoryIndex();
            self::assertSame([], $cacheProperty->getValue($provider));
        });
    }
}
