<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Snapshot;

use Ergonode\AttributeConsumer\Model\Snapshot\SnapshotWriteLock;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SnapshotWriteLockTest extends TestCase
{
    public function testRefreshOwnsLockAcrossNestedWritesAndPruning(): void
    {
        $held = false;
        $backend = $this->createMock(LockManagerInterface::class);
        $backend->expects(self::exactly(2))->method('lock')->willReturnCallback(
            static function () use (&$held): bool {
                if ($held) {
                    return false;
                }
                return $held = true;
            }
        );
        $backend->expects(self::once())->method('unlock')->willReturnCallback(
            static function () use (&$held): bool {
                $held = false;
                return true;
            }
        );
        $owner = new SnapshotWriteLock($backend);
        $contender = new SnapshotWriteLock($backend);
        $owner->execute(function () use ($owner, $contender, &$held): void {
            self::assertTrue($held);
            self::assertSame('saved', $owner->execute(static fn (): string => 'saved'));
            try {
                $contender->execute(static fn () => self::fail('Competing writer entered the refresh.'));
                self::fail('Expected lock conflict.');
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('already running', $exception->getMessage());
            }
            self::assertTrue($held);
            $owner->execute(static function () use (&$held): void {
                self::assertTrue($held, 'Pruning must still own the lock.');
            });
        });
        self::assertFalse($held);
    }

    public function testExceptionReleasesLockAndNextRunAcquiresItAgain(): void
    {
        $backend = $this->createMock(LockManagerInterface::class);
        $backend->expects(self::exactly(2))->method('lock')->willReturn(true);
        $backend->expects(self::exactly(2))->method('unlock')->willReturn(true);
        $lock = new SnapshotWriteLock($backend);
        try {
            $lock->execute(static function (): void {
                throw new RuntimeException('page failed');
            });
            self::fail('Expected original failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('page failed', $exception->getMessage());
        }
        self::assertSame('next', $lock->execute(static fn (): string => 'next'));
    }
}
