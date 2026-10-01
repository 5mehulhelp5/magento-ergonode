<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface AttributeDefinitionSynchronizationInterface
{
    /**
     * Refresh the complete snapshot after a change or deletion; failure preserves its checkpoint.
     *
     * @param bool $force
     * @param bool $writeScope
     * @return void
     */
    public function synchronize(bool $force = false, bool $writeScope = false): void;
}
