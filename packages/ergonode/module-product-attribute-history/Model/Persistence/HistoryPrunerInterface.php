<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model\Persistence;

interface HistoryPrunerInterface
{
    /** Delete complete operations older than the UTC cutoff; preserve the retained stream. */
    public function deleteBefore(string $cutoff): int;
}
