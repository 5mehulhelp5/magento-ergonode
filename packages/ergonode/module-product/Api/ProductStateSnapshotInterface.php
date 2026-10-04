<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

interface ProductStateSnapshotInterface
{
    /** @param list<int> $productIds @return array<int, mixed> Stable persisted state, excluding timestamps. */
    public function get(array $productIds): array;
}
