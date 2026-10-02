<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GalleryWriteLocksTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testRetainsLocksUntilTransactionEndsAndReleasesAfterFailure(bool $fail): void
    {
        $events = [];
        $backend = $this->createMock(LockManagerInterface::class);
        $backend->expects(self::once())->method('lock')->willReturnCallback(
            static function () use (&$events): bool { $events[] = 'lock'; return true; }
        );
        $backend->expects(self::once())->method('unlock')->willReturnCallback(
            static function () use (&$events): bool { $events[] = 'unlock'; return true; }
        );
        $locks = new GalleryWriteLocks($backend);
        try {
            $locks->run(static function () use ($locks, $fail, &$events): void {
                $locks->forPath('catalog/product/shared.jpg', static function () use (&$events): void {
                    $events[] = 'insert';
                });
                // Reentrant registration of the same path must not acquire another lock.
                $locks->forPath('catalog/product/shared.jpg', static fn(): int => 1);
                $events[] = $fail ? 'rollback' : 'commit';
                if ($fail) { throw new RuntimeException('write failed'); }
            });
        } catch (RuntimeException $e) {
            self::assertTrue($fail);
            self::assertSame('write failed', $e->getMessage());
        }
        self::assertSame(['lock', 'insert', $fail ? 'rollback' : 'commit', 'unlock'], $events);
    }

    public static function outcomes(): array
    {
        return ['success' => [false], 'failure' => [true]];
    }
}
