<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Cache;

use Ergonode\Product\Model\ResourceModel\ProductCacheState;

/** Shared by full product batches and independent media updates. Never schedules work. */
class ProductCacheFinalizer
{
    public function __construct(
        private readonly ProductStateSnapshots $snapshots,
        private readonly ProductCacheState $state,
        private readonly ProductCacheInvalidator $cache
    ) {
    }

    /** @param list<int> $ids @return array<int, string> */
    public function begin(array $ids): array
    {
        return $this->state->baseline($this->snapshots->hashes($ids));
    }

    /** @param list<int> $ids @param array<int, string> $before @return array<int, string> Changed persisted states. */
    public function changes(array $ids, array $before): array
    {
        $after = $this->snapshots->hashes($ids);
        $initial = [];
        foreach ($ids as $id) { $initial[$id] = $before[$id] ?? str_repeat('0', 64); }
        $baseline = $this->state->baseline($initial);
        return array_filter($after, static fn(string $hash, int $id): bool => ($baseline[$id] ?? null) !== $hash, ARRAY_FILTER_USE_BOTH);
    }

    /** Publish only completed operations and retain the old baseline if invalidation fails. */
    public function complete(array $changed): void
    {
        $this->cache->invalidate(array_keys($changed));
        $this->state->save($changed);
    }
}
