<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryWriteLockInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

/** Keep native gallery path locks until the caller's transaction has finished. */
class GalleryWriteLocks implements GalleryWriteLockInterface
{
    private bool $active = false;
    private array $held = [];

    public function __construct(private readonly LockManagerInterface $locks)
    {
    }

    public function run(callable $operation): mixed
    {
        if ($this->active) {
            return $operation();
        }
        $this->active = true;
        try {
            return $operation();
        } finally {
            foreach (array_reverse(array_keys($this->held)) as $name) {
                $this->locks->unlock($name);
            }
            $this->held = [];
            $this->active = false;
        }
    }

    public function forPath(string $path, callable $operation): mixed
    {
        $name = 'ergonode_gallery_' . hash('sha256', $path);
        if (isset($this->held[$name])) {
            return $operation();
        }
        if (!$this->locks->lock($name, 30)) {
            throw new LocalizedException(__('Gallery path is being registered.'));
        }
        if ($this->active) {
            $this->held[$name] = true;
            return $operation();
        }
        try {
            return $operation();
        } finally {
            $this->locks->unlock($name);
        }
    }
}
