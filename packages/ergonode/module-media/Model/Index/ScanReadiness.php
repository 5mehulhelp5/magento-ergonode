<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Model\Config\GalleryModeProvider;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Port\ScanStateInterface;

class ScanReadiness
{
    public function __construct(private readonly ScanStateInterface $state, private readonly GalleryModeProvider $mode)
    {
    }

    public function isBlocked(): bool
    {
        return $this->mode->get() === MediaConfig::MODE_SHARED && $this->state->read()['last_completed_at'] === null;
    }
}
