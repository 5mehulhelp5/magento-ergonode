<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface SynchronizationOperationExecutorInterface
{
    /**
     * Check whether an enabled module registered executable operations for the process.
     *
     * @param string $processCode
     * @return bool
     */
    public function isAvailable(string $processCode): bool;

    /**
     * Run a registered synchronization from its current cursor.
     *
     * @param string $processCode
     * @return void
     */
    public function synchronize(string $processCode): void;

    /**
     * Reset a registered synchronization cursor without starting a synchronization.
     *
     * @param string $processCode
     * @return void
     */
    public function resetCursor(string $processCode): void;
}
