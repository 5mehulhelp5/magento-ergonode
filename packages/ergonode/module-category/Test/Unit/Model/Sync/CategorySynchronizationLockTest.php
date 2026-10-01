<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Sync;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CategorySynchronizationLockTest extends TestCase
{
    public function testUsesOneReentrantLockAndAlwaysReleasesIt(): void
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())
            ->method('lock')
            ->with('ergonode_category_synchronization', 0)
            ->willReturn(true);
        $manager->expects(self::once())
            ->method('unlock')
            ->with('ergonode_category_synchronization')
            ->willReturn(true);
        $lock = new CategorySynchronizationLock($manager);

        self::assertSame('nested', $lock->execute(
            fn (): string => $lock->execute(static fn (): string => 'nested')
        ));
    }

    public function testReleasesLockWhenOperationFails(): void
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        $manager->expects(self::once())->method('unlock');
        $lock = new CategorySynchronizationLock($manager);

        $this->expectException(RuntimeException::class);
        $lock->execute(static function (): never {
            throw new RuntimeException('failed');
        });
    }

    public function testRejectsConcurrentSynchronization(): void
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(false);

        $this->expectException(LocalizedException::class);
        (new CategorySynchronizationLock($manager))->execute(static fn (): null => null);
    }
}
