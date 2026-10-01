<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class ConnectionLock
{
    public function __construct(private readonly LockManagerInterface $lockManager)
    {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(string $profile, callable $operation): mixed
    {
        $name = 'ergonode_rest_' . $profile;
        if (!$this->lockManager->lock($name, 35)) {
            throw new LocalizedException(__('The Ergonode connection is being renewed. Retry shortly.'));
        }
        try {
            return $operation();
        } finally {
            $this->lockManager->unlock($name);
        }
    }
}
