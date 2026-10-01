<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Port;

interface AttributeDefinitionCheckRecorderInterface
{
    /**
     * Record running, changes_detected, initialized, changed, no_changes or failed.
     * Recording failures must not interrupt synchronization.
     */
    public function record(string $status): void;
}
