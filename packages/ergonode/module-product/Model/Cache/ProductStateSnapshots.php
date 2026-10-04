<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Cache;

use Ergonode\Product\Api\ProductStateSnapshotInterface;

class ProductStateSnapshots
{
    /** @param array<string, ProductStateSnapshotInterface> $providers */
    public function __construct(private readonly array $providers = [])
    {
    }

    /** @param list<int> $ids @return array<int, string> */
    public function hashes(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $states = array_fill_keys($ids, []);
        $providers = $this->providers;
        ksort($providers);
        foreach ($providers as $key => $provider) {
            $rows = $provider->get($ids);
            foreach ($ids as $id) {
                $states[$id][$key] = $rows[$id] ?? [];
            }
        }
        return array_map(static fn(array $state): string => hash('sha256', serialize($state)), $states);
    }
}
