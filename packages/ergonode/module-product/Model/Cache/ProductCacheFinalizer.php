<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Cache;

/** Compares persisted product data within one pass; never stores cache state or schedules work. */
class ProductCacheFinalizer
{
    public function __construct(
        private readonly ProductStateSnapshots $snapshots,
        private readonly ProductCacheInvalidator $cache
    ) {
    }

    /** @param list<int> $ids @return array<int, string> */
    public function begin(array $ids): array
    {
        return $this->snapshots->hashes($ids);
    }

    /** @param list<int> $ids @param array<int, string> $before @return array<int, string> Changed persisted states. */
    public function changes(array $ids, array $before): array
    {
        $after = $this->snapshots->hashes($ids);
        return array_filter($after, static fn(string $hash, int $id): bool => ($before[$id] ?? null) !== $hash, ARRAY_FILTER_USE_BOTH);
    }

    /** Invalidate completed changes; callers log failures without retaining work for another pass. */
    public function complete(array $changed): void
    {
        $this->cache->invalidate(array_keys($changed));
    }
}
