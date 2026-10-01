<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

class MappingSynchronization implements MappingSynchronizationInterface
{
    public function __construct(
        private readonly CategoryMappedAttributeBackfiller $backfiller,
        private readonly CategorySynchronizationLock $lock
    ) {
    }

    public function execute(callable $save): array
    {
        return $this->lock->execute(function () use ($save): array {
            $stats = $save();
            $stats['value_sync'] = $this->backfiller->execute();
            return $stats;
        });
    }
}
