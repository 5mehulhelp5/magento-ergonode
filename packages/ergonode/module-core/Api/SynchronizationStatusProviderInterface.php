<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface SynchronizationStatusProviderInterface
{
    /**
     * Return synchronization capabilities registered by enabled domain modules.
     *
     * @return list<array{
     *     process_code: string,
     *     label: string,
     *     description: string,
     *     cursor: string|null,
     *     synced_at: string|null,
     *     monitor_only?: bool
     * }>
     */
    public function getList(): array;
}
