<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface ScanRequesterInterface
{
    /**
     * Request a background scan; duplicate requests keep the current run.
     *
     * @return void
     */
    public function request(): void;
}
