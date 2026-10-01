<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class CategorySynchronizationLock
{
    private const string LOCK_NAME = 'ergonode_category_synchronization';

    private int $depth = 0;

    public function __construct(
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function isRunning(): bool
    {
        return $this->lockManager->isLocked(self::LOCK_NAME);
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        if ($this->depth > 0) {
            return $operation();
        }
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Ergonode category synchronization is already running.'));
        }

        $this->depth++;
        try {
            return $operation();
        } finally {
            $this->depth--;
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
