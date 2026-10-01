<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\Core\Api\SynchronizationOperationInterface;

class CategoryDataSynchronizationOperation implements SynchronizationOperationInterface
{
    public function __construct(
        private readonly CategoryDataSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryDataSyncCursorResetter $cursorResetter
    ) {
    }

    public function synchronize(): void
    {
        $this->synchronizationProcess->execute();
    }

    public function resetCursor(): void
    {
        $this->cursorResetter->reset();
    }
}
