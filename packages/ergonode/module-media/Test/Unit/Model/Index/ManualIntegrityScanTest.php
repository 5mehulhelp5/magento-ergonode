<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Index\LocalFileScanner;
use Ergonode\Media\Model\Index\MaterializationIntegrityVerifier;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MaterializationAudit;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ManualIntegrityScanTest extends TestCase
{
    public function testManualScanFindsSameSizeAndTimestampCorruptionWithoutRepairingOrDispatching(): void
    {
        $root = sys_get_temp_dir() . '/ergonode-audit-' . bin2hex(random_bytes(8));
        mkdir($root . '/catalog/product/cache', 0777, true);
        $path = 'catalog/product/photo.jpg';
        $missing = 'catalog/product/missing.jpg';
        $expected = hash('sha256', 'first', true);
        file_put_contents($root . '/' . $path, 'other');
        touch($root . '/' . $path, 1234567890);
        file_put_contents($root . '/catalog/product/cache/resized.jpg', 'cache');
        try {
            $directories = $this->createStub(DirectoryList::class);
            $directories->method('getPath')->willReturn($root);
            $files = new LocalFiles($directories, new File());
            $rows = [$path => ['path' => $path, 'content_hash' => $expected, 'size' => 5,
                'modified_at' => 1234567890], $missing => ['path' => $missing]];
            $index = $this->createStub(LocalFileIndexInterface::class);
            $index->method('get')->willReturnCallback(static fn (string $p): ?array => $rows[$p] ?? null);
            $index->method('save')->willReturnCallback(static function ($p, $hash, $size, $mtime) use (&$rows): void {
                $rows[$p] = ['path' => $p, 'content_hash' => $hash, 'size' => $size, 'modified_at' => $mtime];
            });
            $index->method('page')->willReturnCallback(static fn (string $after): array => array_values(array_filter(
                $rows, static fn (array $r): bool => strcmp($r['path'], $after) > 0
            )));
            $index->method('remove')->willReturnCallback(static function ($p) use (&$rows): void { unset($rows[$p]); });
            $references = $this->createMock(MaterializationAudit::class);
            $references->expects(self::exactly(2))->method('page')->willReturnOnConsecutiveCalls([
                $this->reference(1, $path, $expected), $this->reference(2, $path, $expected),
                $this->reference(3, $missing, $expected),
            ], []);
            $logger = $this->createMock(LoggerInterface::class);
            $findings = [];
            $logger->expects(self::exactly(2))->method('warning')->willReturnCallback(
                static function (string $message, array $context) use (&$findings): void { $findings[] = $context; }
            );
            $logger->expects(self::never())->method('error');
            $state = $this->createMock(ScanStateInterface::class);
            $state->method('read')->willReturn(['status' => 'audit_pending']);
            $state->expects(self::once())->method('begin')->with(0, true);
            $state->expects(self::once())->method('complete')->with(true, self::callback(
                static fn (?string $report): bool => str_contains($report ?? '', '1 content mismatches, 1 missing files, 0 unreadable')
            ));
            $state->expects(self::never())->method('fail');
            $verifier = new MaterializationIntegrityVerifier($references, $files, $logger);
            $scanner = new LocalFileScanner($this->publisher(), $files, $index, $this->locks(), $state, $verifier, $logger);
            self::assertSame(['indexed' => 1, 'reused' => 0, 'removed' => 1,
                'mismatched' => 1, 'missing' => 1, 'unreadable' => 0], $scanner->scan(true));
            self::assertSame(hash('sha256', 'other', true), $rows[$path]['content_hash']);
            self::assertSame('other', file_get_contents($root . '/' . $path));
            self::assertSame('cache', file_get_contents($root . '/catalog/product/cache/resized.jpg'));
            self::assertFileDoesNotExist($root . '/' . $missing);
            self::assertSame(['mismatched', 'missing'], array_column($findings, 'reason'));
            self::assertSame(bin2hex($expected), $findings[0]['expected_sha256']);
            self::assertSame('source.jpg', $findings[0]['source_path']);
        } finally {
            unlink($root . '/' . $path);
            unlink($root . '/catalog/product/cache/resized.jpg');
            rmdir($root . '/catalog/product/cache');
            rmdir($root . '/catalog/product');
            rmdir($root . '/catalog');
            rmdir($root);
        }
    }

    public function testUnreadableFileIsLoggedOnceAndOtherFilesAreStillVerified(): void
    {
        $bad = 'catalog/product/bad.jpg';
        $good = 'catalog/product/good.jpg';
        $hash = hash('sha256', 'valid', true);
        $files = $this->createMock(LocalFiles::class);
        $files->method('paths')->willReturn([$bad, $good]);
        $files->method('stat')->willReturnCallback(static function (string $path) use ($bad): array {
            if ($path === $bad) { throw new RuntimeException('Permission denied'); }
            return ['size' => 5, 'modified_at' => 2];
        });
        $files->expects(self::once())->method('hash')->with($good)->willReturn($hash);
        $index = $this->createMock(LocalFileIndexInterface::class);
        $index->method('page')->willReturnOnConsecutiveCalls([['path' => $bad], ['path' => $good]], []);
        $index->expects(self::once())->method('save')->with($good, $hash, 5, 2);
        $index->expects(self::never())->method('remove');
        $refs = $this->createStub(MaterializationAudit::class);
        $refs->method('page')->willReturnOnConsecutiveCalls([
            $this->reference(1, $bad, $hash), $this->reference(2, $good, $hash),
        ], []);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(
            static fn (array $context): bool => $context['path'] === $bad && $context['exception']->getMessage() === 'Permission denied'
        ));
        $logger->expects(self::never())->method('warning');
        $state = $this->createMock(ScanStateInterface::class);
        $state->expects(self::once())->method('complete')->with(true, self::callback(
            static fn (?string $s): bool => str_contains($s ?? '', '1 unreadable files')
        ));
        $state->expects(self::never())->method('fail');
        $scanner = new LocalFileScanner($this->publisher(), $files, $index, $this->locks(), $state,
            new MaterializationIntegrityVerifier($refs, $files, $logger), $logger);
        self::assertSame(1, $scanner->scan(false, true)['unreadable']);
    }

    public function testMappedFileOutsideTraversalIsHashedWithoutTrustingAnOldIndex(): void
    {
        $path = 'catalog/product/archive/photo.jpg';
        $files = $this->createMock(LocalFiles::class);
        $files->method('stat')->willReturn(['size' => 5, 'modified_at' => 123]);
        $files->expects(self::once())->method('hash')->with($path)->willReturn(hash('sha256', 'other', true));
        $refs = $this->createStub(MaterializationAudit::class);
        $refs->method('page')->willReturnOnConsecutiveCalls([
            $this->reference(1, $path, hash('sha256', 'first', true)),
        ], []);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        self::assertSame(['mismatched' => 1, 'missing' => 0, 'unreadable' => 0],
            (new MaterializationIntegrityVerifier($refs, $files, $logger))->verify());
    }

    public function testCompletedVerificationIsNotRunAgainByTheWorker(): void
    {
        $files = $this->createMock(LocalFiles::class);
        $files->expects(self::never())->method('paths');
        $state = $this->createStub(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'audited']);
        $scanner = new LocalFileScanner($this->publisher(), $files,
            $this->createStub(LocalFileIndexInterface::class), $this->locks(), $state);
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 0], $scanner->scan(true));
    }

    public function testInterruptedVerificationIsReportedWithoutAnAutomaticRestart(): void
    {
        $files = $this->createMock(LocalFiles::class);
        $files->expects(self::never())->method('paths');
        $state = $this->createMock(ScanStateInterface::class);
        $state->method('read')->willReturn(['status' => 'auditing']);
        $state->expects(self::never())->method('begin');
        $state->expects(self::once())->method('fail')->with(
            'File verification was interrupted. Request a new verification manually.'
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $scanner = new LocalFileScanner($this->publisher(), $files,
            $this->createStub(LocalFileIndexInterface::class), $this->locks(), $state, null, $logger);
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 0], $scanner->scan(true));
    }

    public function testFatalTraversalFailureIsLoggedAndDoesNotDispatchWork(): void
    {
        $files = $this->createStub(LocalFiles::class);
        $files->method('paths')->willReturnCallback(static function (): iterable {
            throw new RuntimeException('Unreadable directory');
            yield '';
        });
        $state = $this->createMock(ScanStateInterface::class);
        $state->expects(self::never())->method('complete');
        $state->expects(self::once())->method('fail')->with('Unreadable directory');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $refs = $this->createStub(MaterializationAudit::class);
        $scanner = new LocalFileScanner($this->publisher(), $files,
            $this->createStub(LocalFileIndexInterface::class), $this->locks(), $state,
            new MaterializationIntegrityVerifier($refs, $files, $logger), $logger);
        $this->expectException(RuntimeException::class);
        $scanner->scan(false, true);
    }

    private function reference(int $id, string $path, string $hash): array
    {
        return ['id' => $id, 'path' => $path, 'content_hash' => $hash, 'asset_id' => 8,
            'source_path' => 'source.jpg', 'product_id' => 23];
    }

    private function locks(): LockManagerInterface
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock')->with(LocalFileScanner::LOCK);
        return $locks;
    }

    private function publisher(): QueuePublisher
    {
        $publisher = $this->createMock(QueuePublisher::class);
        $publisher->expects(self::never())->method('dispatch');
        return $publisher;
    }
}
