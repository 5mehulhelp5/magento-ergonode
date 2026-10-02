<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface GalleryWriteLockInterface
{
    /** Retain acquired gallery path locks until the operation commits or rolls back. */
    public function run(callable $operation): mixed;
}
