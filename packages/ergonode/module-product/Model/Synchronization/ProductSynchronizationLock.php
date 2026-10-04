<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Synchronization;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class ProductSynchronizationLock
{
    private int $depth = 0;
    private const string NAME = 'ergonode_product_synchronization';

    public function __construct(private readonly LockManagerInterface $locks)
    {
    }

    public function run(callable $operation, int $timeout = 0): mixed
    {
        if ($this->depth === 0 && !$this->locks->lock(self::NAME, $timeout)) {
            throw new LocalizedException(__('Product synchronization is already running in the background. Try again later.'));
        }
        $this->depth++;
        try {
            return $operation();
        } finally {
            if (--$this->depth === 0) { $this->locks->unlock(self::NAME); }
        }
    }
}
