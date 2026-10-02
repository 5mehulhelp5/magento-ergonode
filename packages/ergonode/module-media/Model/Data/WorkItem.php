<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Data;

class WorkItem
{
    public function __construct(
        public readonly int $productId,
        public readonly string $leaseToken,
        public readonly int $attemptCount
    ) {
    }
}
