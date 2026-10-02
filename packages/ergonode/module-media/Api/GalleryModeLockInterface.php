<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface GalleryModeLockInterface
{
    /** @return string|null */
    public function getLockedMode(): ?string;

    /**
     * @param string $mode
     * @return void
     */
    public function lock(string $mode): void;
}
