<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\Core\Model\Import\CursorStorage;

class CategoryDataSyncCursorResetter
{
    public function __construct(
        private readonly CursorStorage $cursorStorage,
        private readonly CategorySynchronizationLock $synchronizationLock
    ) {
    }

    public function reset(): void
    {
        $this->synchronizationLock->execute(function (): void {
            $this->cursorStorage->reset(CategoryEntityStreamImporter::PROCESS_CODE);
        });
    }
}
