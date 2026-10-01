<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Import;

use Ergonode\CategoryConsumer\Model\Import\CategoryTreeSyncCursorResetter;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\Core\Model\Import\CursorStorage;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryTreeSyncCursorResetterTest extends TestCase
{
    public function testResetsOnlySupportedCategoryTreeStreamCursorUnderSharedLock(): void
    {
        $cursorStorage = $this->createMock(CursorStorage::class);
        $cursorStorage->expects(self::once())->method('reset')->with('category_tree_stream');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method('lock')->willReturn(true);
        $lockManager->expects(self::once())->method('unlock');

        (new CategoryTreeSyncCursorResetter(
            $cursorStorage,
            new CategorySynchronizationLock($lockManager)
        ))->reset();
    }
}
