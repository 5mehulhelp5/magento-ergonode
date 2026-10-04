<?php

declare(strict_types=1);

namespace Ergonode\Product\Plugin;

use Ergonode\Product\Model\Synchronization\ProductSynchronizationLock;

/** Acquire before claiming work, so a busy worker cannot consume another batch's inline media. */
class SerializeProductWorkers
{
    public function __construct(private readonly ProductSynchronizationLock $lock)
    {
    }

    public function aroundProcess($subject, callable $proceed, string $message): void
    {
        // Wait before claiming an item. Waiting is not a second attempt at a failed operation.
        $this->lock->run(fn() => $proceed($message), -1);
    }
}
