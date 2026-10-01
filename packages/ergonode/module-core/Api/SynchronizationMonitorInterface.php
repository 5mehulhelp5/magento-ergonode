<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface SynchronizationMonitorInterface
{
    /**
     * Return registered processes with cursor state and recorded execution evidence.
     *
     * @return list<array<string, mixed>>
     */
    public function getList(): array;
}
