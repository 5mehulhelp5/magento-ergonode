<?php

declare(strict_types=1);

namespace Ergonode\Media\Cron;

/** Compatibility for previously scheduled cron rows: failed media is never resubmitted. */
class RecoverWork
{
    public function execute(): void
    {
    }
}
