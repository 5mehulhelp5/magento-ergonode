<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface SynchronizationOperationInterface
{
    /**
     * Run the registered synchronization from its current cursor.
     *
     * @return void
     */
    public function synchronize(): void;

    /**
     * Reset the registered synchronization cursor without starting a synchronization.
     *
     * @return void
     */
    public function resetCursor(): void;
}
