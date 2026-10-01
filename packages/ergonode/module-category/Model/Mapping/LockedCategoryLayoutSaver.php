<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Mapping;

use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

class LockedCategoryLayoutSaver implements CategoryLayoutSaverInterface
{
    public function __construct(
        private readonly CategoryLayoutSaver $categoryLayoutSaver,
        private readonly CategorySynchronizationLock $synchronizationLock,
        private readonly CategoryTreeSourceState $sourceAvailability
    ) {
    }

    public function save(int $categoryTreeId, array $items, array $visibility = []): array
    {
        return $this->synchronizationLock->execute(
            function () use ($categoryTreeId, $items, $visibility): array {
                $this->sourceAvailability->assertCanUseSnapshot($categoryTreeId);

                return $this->categoryLayoutSaver->save($categoryTreeId, $items, $visibility);
            }
        );
    }
}
