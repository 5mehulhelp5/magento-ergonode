<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Index\LocalFileScanner;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\Index\ScanRequest;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use ArrayIterator;
use RuntimeException;

class ScanLifecycleTest extends TestCase
{
    public function testACompleteTraversalCertifiesReadinessEvenWhenTheEstimateIsWrong(): void
    {
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturn(['catalog/product/one.jpg', 'catalog/product/two.jpg']);
        $files->method('stat')->willReturn(['size' => 8, 'modified_at' => 1]);
        $files->method('hash')->willReturn(str_repeat('a', 32));
        $index = $this->createStub(LocalFileIndexInterface::class);
        $index->method('page')->willReturn([]);
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('estimate')->willReturn(1);
        $state->expects(self::once())->method('begin')->with(1);
        $state->expects(self::once())->method('progress')->with(['indexed' => 2, 'reused' => 0, 'removed' => 0], 16);
        $state->expects(self::once())->method('complete');
        $state->expects(self::never())->method('fail');
        $scanner = new LocalFileScanner($this->createStub(QueuePublisher::class), $files, $index, $this
            ->locks(), $state);
        self::assertSame(['indexed' => 2, 'reused' => 0, 'removed' => 0], $scanner->scan());
    }

    public function testFailureAtEstimatedTotalDoesNotCertifyAFullScan(): void
    {
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturnCallback(static function (): iterable {
            yield 'catalog/product/one.jpg';
            throw new RuntimeException('Unreadable next directory');
        });
        $files->method('stat')->willReturn(['size' => 8, 'modified_at' => 1]);
        $files->method('hash')->willReturn(str_repeat('a', 32));
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('estimate')->willReturn(1);
        $state->expects(self::once())->method('progress')->with(['indexed' => 1, 'reused' => 0, 'removed' => 0], 8);
        $state->expects(self::once())->method('fail')->with('Unreadable next directory');
        $state->expects(self::never())->method('complete');
        $scanner = new LocalFileScanner(
            $this->createStub(QueuePublisher::class),
            $files,
            $this->createStub(LocalFileIndexInterface::class),
            $this->locks(),
            $state
        );
        $this->expectException(RuntimeException::class);
        $scanner->scan();
    }

    public function testAnEmptySuccessfulTraversalIsAFullScan(): void
    {
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturn([]);
        $state = $this->createMock(ScanStateInterface::class);
        $state->expects(self::once())->method('complete');
        $scanner = new LocalFileScanner(
            $this->createStub(QueuePublisher::class),
            $files,
            $this->createStub(LocalFileIndexInterface::class),
            $this->locks(),
            $state
        );
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 0], $scanner->scan());
    }

    public function testCronDoesNotRepeatCompletedRuns(): void
    {
        $files = $this->createMock(LocalFiles::class);
        $files->expects(self::never())->method('paths');
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'complete']);
        $scanner = new LocalFileScanner(
            $this->createStub(QueuePublisher::class),
            $files,
            $this->createStub(LocalFileIndexInterface::class),
            $this->locks(),
            $state
        );
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 0], $scanner->scan(true));
    }

    public function testDuplicatePendingRequestDoesNotResetProgress(): void
    {
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'pending']);
        $state->expects(self::never())->method('request');
        (new ScanRequest($state, $this->locks()))->request();
    }

    public function testRequestCannotResetAnActiveScan(): void
    {
        $state = $this->createMock(ScanStateInterface::class);
        $state->expects(self::never())->method('request');
        $state->method('read')->willReturn(['status' => 'running']);
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(false);
        (new ScanRequest($state, $locks))->request();
    }

    public function testAContendedIdleScannerDoesNotSilentlyLoseTheRequest(): void
    {
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'required']);
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(false);
        $this->expectException(LocalizedException::class);
        (new ScanRequest($state, $locks))->request();
    }

    public function testCronRestartsAnInterruptedRunAfterItsLockWasReleased(): void
    {
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturn([]);
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'running']);
        $state->expects(self::once())->method('begin');
        $state->expects(self::once())->method('complete');
        $scanner = new LocalFileScanner(
            $this->createStub(QueuePublisher::class),
            $files,
            $this->createStub(LocalFileIndexInterface::class),
            $this->locks(),
            $state
        );
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 0], $scanner->scan(true));
    }

    private function locks(): LockManagerInterface
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock')->with(LocalFileScanner::LOCK);
        return $locks;
    }
    public function testCompletedScanDispatchesWaitingWorkWithoutAutomaticRecovery(): void
    {
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'pending']);
        $state->expects(self::once())->method('complete');
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturn(new ArrayIterator([]));
        $index = $this->createStub(LocalFileIndexInterface::class);
        $index->method('page')->willReturn([]);
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::once())->method('dispatch');
        (new LocalFileScanner($publisher, $files, $index, $this->locks(), $state))->scan(true);
    }
}
