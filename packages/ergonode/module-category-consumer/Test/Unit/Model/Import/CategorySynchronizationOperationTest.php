<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Import;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncCursorResetterInterface;
use Ergonode\CategoryConsumer\Model\Import\CategorySynchronizationOperation;
use PHPUnit\Framework\TestCase;

class CategorySynchronizationOperationTest extends TestCase
{
    public function testDelegatesSynchronizationAndCursorReset(): void
    {
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->with(false);
        $resetter = $this->createMock(CategoryTreeSyncCursorResetterInterface::class);
        $resetter->expects(self::once())->method('reset');
        $operation = new CategorySynchronizationOperation($process, $resetter);

        $operation->synchronize();
        $operation->resetCursor();
    }
}
