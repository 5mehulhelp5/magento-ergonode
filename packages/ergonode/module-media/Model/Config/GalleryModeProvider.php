<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Config;

use Ergonode\Media\Api\GalleryModeLockInterface;

class GalleryModeProvider
{
    public function __construct(private readonly MediaConfig $config, private readonly GalleryModeLockInterface $lock)
    {
    }
    public function get(): string
    {
        return $this->lock->getLockedMode() ?? $this->config->getMode();
    }
}
