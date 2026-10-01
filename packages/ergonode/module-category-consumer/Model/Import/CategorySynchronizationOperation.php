<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncCursorResetterInterface;
use Ergonode\Core\Api\SynchronizationOperationInterface;

class CategorySynchronizationOperation implements SynchronizationOperationInterface
{
    public function __construct(
        private readonly CategoryStructureSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryTreeSyncCursorResetterInterface $cursorResetter
    ) {
    }

    public function synchronize(): void
    {
        $this->synchronizationProcess->execute(false);
    }

    public function resetCursor(): void
    {
        $this->cursorResetter->reset();
    }
}
