<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Snapshot;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

/** Serializes snapshot writers, including nested writes within an option refresh. */
class SnapshotWriteLock
{
    private const string LOCK_NAME = 'ergonode_attribute_snapshot_write';

    private int $depth = 0;

    public function __construct(private readonly LockManagerInterface $locks)
    {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        if ($this->depth === 0 && !$this->locks->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Attribute snapshot synchronization is already running.'));
        }
        ++$this->depth;
        try {
            return $operation();
        } finally {
            if (--$this->depth === 0) {
                $this->locks->unlock(self::LOCK_NAME);
            }
        }
    }
}
