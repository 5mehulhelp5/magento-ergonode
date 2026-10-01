<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Language\Model\Mapping\MappingLock;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MappingLockTest extends TestCase
{
    public function testNestedStateReadUsesTheWriteLockAndReleasesItAfterFailure(): void
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())->method('lock')->willReturn(true);
        $manager->expects(self::once())->method('unlock');
        $lock = new MappingLock($manager);
        $this->expectException(RuntimeException::class);
        $lock->run(static fn () => $lock->run(static fn () => throw new RuntimeException('write failed')));
    }

    public function testBusyLockNeverRunsTheOperation(): void
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())->method('lock')->willReturn(false);
        $manager->expects(self::never())->method('unlock');
        $this->expectException(LocalizedException::class);
        (new MappingLock($manager))->run(static function (): void {
            self::fail('Operation ran without its lock.');
        });
    }
}
