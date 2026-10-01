<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Mapping;

use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;

use Ergonode\Category\Model\Mapping\LockedCategoryLayoutSaver;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class LockedCategoryLayoutSaverTest extends TestCase
{
    public function testMissingSourceRejectsMappingChangesBeforePersistenceAndReleasesLock(): void
    {
        $saver = $this->createMock(CategoryLayoutSaver::class);
        $saver->expects(self::never())->method('save');
        $availability = $this->createStub(CategoryTreeSourceState::class);
        $availability->method('assertCanUseSnapshot')->willThrowException(
            new LocalizedException(__('Missing source.'))
        );
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        $lock->expects(self::once())->method('unlock');

        $this->expectException(LocalizedException::class);
        (new LockedCategoryLayoutSaver($saver, new CategorySynchronizationLock($lock), $availability))->save(7, []);
    }

    public function testDelegatesLayoutAndTargetedAttributeWritesUnderSharedLock(): void
    {
        $layoutSaver = $this->createMock(CategoryLayoutSaver::class);
        $layoutSaver->expects(self::once())->method('save')->with(7, [], [])->willReturn([
            'updated' => 1,
            'unchanged' => 0,
            'attribute_values' => 2,
        ]);
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method('lock')->willReturn(true);
        $lockManager->expects(self::once())->method('unlock');

        self::assertSame([
            'updated' => 1,
            'unchanged' => 0,
            'attribute_values' => 2,
        ], (new LockedCategoryLayoutSaver(
            $layoutSaver,
            new CategorySynchronizationLock($lockManager),
            $this->createStub(CategoryTreeSourceState::class)
        ))->save(7, [], []));
    }
}
