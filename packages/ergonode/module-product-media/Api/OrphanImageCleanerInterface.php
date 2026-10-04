<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface OrphanImageCleanerInterface
{
    /** @param list<string> $paths Delete only after commit and only if no product or attribute uses the file. */
    public function schedule(int $productId, array $paths): void;
}
