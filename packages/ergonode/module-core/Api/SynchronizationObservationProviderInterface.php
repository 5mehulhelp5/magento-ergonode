<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface SynchronizationObservationProviderInterface
{
    /**
     * Return the latest recorded execution, independently of cursor movement.
     *
     * @return array{status: string, started_at: ?string, completed_at: ?string, changed_at: ?string}
     */
    public function getStatus(): array;
}
