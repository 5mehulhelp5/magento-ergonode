<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class MappingLock
{
    private const string NAME = 'ergonode_language_mapping';

    private bool $held = false;

    public function __construct(private readonly LockManagerInterface $lockManager)
    {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws LocalizedException
     */
    public function run(callable $operation): mixed
    {
        if ($this->held) {
            return $operation();
        }
        if (!$this->lockManager->lock(self::NAME, 5)) {
            throw new LocalizedException(__('Language mappings are being changed. Please try again.'));
        }
        $this->held = true;
        try {
            return $operation();
        } finally {
            $this->held = false;
            $this->lockManager->unlock(self::NAME);
        }
    }
}
