<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;

class ProductCacheInvalidator
{
    public function __construct(private readonly CacheInterface $cache, private readonly ManagerInterface $events)
    {
    }

    /** @param list<int> $productIds */
    public function invalidate(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $identity = new ProductCacheIdentity($ids);
        $this->cache->clean($identity->getIdentities());
        $this->events->dispatch('clean_cache_by_tags', ['object' => $identity]);
    }
}
