<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface AutomaticSynchronizationInterface
{
    /**
     * Check the read mode and a fresh remote connection before starting scheduled work.
     * Expected connection failures return false; unexpected failures remain visible.
     *
     * @return bool Whether the active connection currently permits automatic imports.
     */
    public function isAllowed(): bool;
}
