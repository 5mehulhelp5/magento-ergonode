<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;

class CategoryCacheInvalidator
{
    private const string BLOCK_HTML_CACHE = 'block_html';
    private const string FULL_PAGE_CACHE = 'full_page';

    private int $depth = 0;
    /** @var array<int, int> */
    private array $pending = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TypeListInterface $typeList,
        private readonly ManagerInterface $eventManager,
        private readonly MagentoCategoryProvider $categoryProvider
    ) {
    }

    /**
     * Keep the working tree index and defer cache invalidation until the operation has completed.
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function defer(callable $operation): mixed
    {
        $this->depth++;
        try {
            return $operation();
        } finally {
            $this->depth--;
            if ($this->depth === 0) {
                $ids = array_values($this->pending);
                $this->pending = [];
                $this->invalidateCategories($ids);
            }
        }
    }

    /**
     * @param int[] $categoryIds
     */
    public function invalidateCategories(array $categoryIds): void
    {
        $tags = [Category::CACHE_TAG];
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if ($categoryIds === []) {
            return;
        }

        foreach ($categoryIds as $categoryId) {
            $tags[] = Category::CACHE_TAG . '_' . $categoryId;
        }

        if ($this->depth > 0) {
            foreach ($categoryIds as $categoryId) {
                $this->pending[$categoryId] = $categoryId;
            }
            return;
        }
        $this->categoryProvider->clearCache();
        $this->cleanTags($tags);
        $this->typeList->cleanType(self::BLOCK_HTML_CACHE);
        $this->typeList->cleanType(self::FULL_PAGE_CACHE);
    }

    /** Refresh data changed during a pass, without flushing frontend caches mid-operation. */
    public function refreshCategoryIndex(): void
    {
        if ($this->pending !== []) {
            $this->categoryProvider->clearCache();
        }
    }

    /**
     * @param string[] $tags
     */
    private function cleanTags(array $tags): void
    {
        $this->cache->clean($tags);
        $this->eventManager->dispatch(
            'clean_cache_by_tags',
            ['object' => new CategoryCacheIdentity($tags)]
        );
    }
}
