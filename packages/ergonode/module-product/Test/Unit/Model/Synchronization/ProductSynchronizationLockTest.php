<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Unit\Model\Synchronization;

use Ergonode\Product\Model\Synchronization\ProductSynchronizationLock;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductSynchronizationLockTest extends TestCase
{
    public function testNestedInlineStagesUseOneLockAndFailureAlwaysReleasesIt(): void
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects(self::once())->method('lock')->with('ergonode_product_synchronization', 0)->willReturn(true);
        $locks->expects(self::once())->method('unlock')->with('ergonode_product_synchronization')->willReturn(true);
        $lock = new ProductSynchronizationLock($locks);
        $this->expectException(RuntimeException::class);
        $lock->run(fn() => $lock->run(static function (): void { throw new RuntimeException('failure'); }));
    }

    public function testBusyWorkerDoesNotStartProductWrites(): void
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(false);
        $locks->expects(self::never())->method('unlock');
        $lock = new ProductSynchronizationLock($locks);
        $called = false;
        try { $lock->run(function () use (&$called): void { $called = true; }); self::fail('Expected busy error'); }
        catch (LocalizedException $error) { self::assertStringContainsString('already running', $error->getMessage()); }
        self::assertFalse($called);
    }
}
