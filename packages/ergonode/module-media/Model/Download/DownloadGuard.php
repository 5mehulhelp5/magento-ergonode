<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Download;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class DownloadGuard
{
    public function __construct(private readonly CacheInterface $cache, private readonly LockManagerInterface $locks)
    {
    }
    /**
     * Execute one rate-limited Ergonode media request.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $slot = null;
        for ($i = 0; $i < 6; $i++) {
            $name = 'ergonode_media_slot_' . $i;
            if ($this->locks->lock($name, 0)) {
                $slot = $name;
                break;
            }
        }
        if ($slot === null) {
            throw new LocalizedException(__('All Ergonode media download slots are busy.'));
        }
        try {
            $key = 'ergonode_media_rate_' . (int)floor(time() / 60);
            $lock = $key . '_lock';
            if (!$this->locks->lock($lock, 5)) {
                throw new LocalizedException(__('Ergonode media rate limiter is busy.'));
            }
            try {
                $count = (int)$this->cache->load($key);
                if ($count >= 250) {
                    throw new LocalizedException(__('Ergonode media request limit has been reached.'));
                } $this->cache->save((string)($count + 1), $key, [], 120);
            } finally {
                $this->locks->unlock($lock);
            }
            return $operation();
        } finally {
            $this->locks->unlock($slot);
        }
    }
}
