<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Materialization;

use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Materialization\OrphanImageCleaner;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\ResourceModel\OrphanImageReferences;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\CallbackPool;
use Magento\Framework\Model\ExecuteCommitCallbacks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrphanImageCleanerTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testActualFileIsDeletedOnlyAfterCommitAndWithoutOtherUses(bool $rollback, bool $otherUse): void
    {
        $root = sys_get_temp_dir() . '/ergonode-orphan-' . bin2hex(random_bytes(8));
        mkdir($root . '/catalog/product', 0777, true);
        $path = 'catalog/product/shared.jpg';
        file_put_contents($root . '/' . $path, 'shared image');
        $level = 1;
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('getTransactionLevel')->willReturnCallback(static function () use (&$level): int { return $level; });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturn($root);
        $references = $this->createMock(OrphanImageReferences::class);
        $references->expects($rollback ? self::never() : self::once())->method('isUsed')->with($path)->willReturn($otherUse);
        $references->expects(!$rollback && !$otherUse ? self::once() : self::never())->method('forgetUnusedFile')->with($path);
        $index = $this->createMock(LocalFileIndexInterface::class);
        $index->expects(!$rollback && !$otherUse ? self::once() : self::never())->method('remove')->with($path);
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $cleaner = new OrphanImageCleaner($resource, $references, new LocalFiles($directories, new File()), new File(),
            $index, new GalleryWriteLocks($locks), $logger);
        try {
            $cleaner->schedule(23, [$path, $path]);
            self::assertFileExists($root . '/' . $path);
            $callbacks = new ExecuteCommitCallbacks($logger);
            if ($rollback) {
                $callbacks->afterRollBack($connection, $connection);
            } else {
                $level = 0;
                // Use Magento's real callback executor; no database transaction is executed by this test.
                $callbacks->afterCommit($connection, $connection);
            }
            if ($rollback || $otherUse) {
                self::assertSame('shared image', file_get_contents($root . '/' . $path));
            } else {
                self::assertFileDoesNotExist($root . '/' . $path);
            }
        } finally {
            CallbackPool::clear(spl_object_hash($connection));
            if (is_file($root . '/' . $path)) { unlink($root . '/' . $path); }
            rmdir($root . '/catalog/product'); rmdir($root . '/catalog'); rmdir($root);
        }
    }

    public static function outcomes(): array
    {
        return ['last product committed' => [false, false], 'another product still uses it' => [false, true],
            'role transaction rolled back' => [true, false]];
    }

    public function testDeletionFailureIsLoggedOnceAndDoesNotRetryOrClearMappings(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $references = $this->createMock(OrphanImageReferences::class);
        $references->method('isUsed')->willReturn(false);
        $references->expects(self::never())->method('forgetUnusedFile');
        $files = $this->createStub(LocalFiles::class);
        $files->method('stat')->willReturn(['size' => 1, 'modified_at' => 1]);
        $files->method('absolute')->willReturn('/temporary-test/photo.jpg');
        $driver = $this->createMock(File::class);
        $driver->expects(self::once())->method('deleteFile')->willReturn(false);
        $index = $this->createMock(LocalFileIndexInterface::class);
        $index->expects(self::never())->method('remove');
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Unable to remove unused product media.', self::callback(
            static fn (array $c): bool => $c['product_id'] === 23 && $c['path'] === 'catalog/product/photo.jpg'
                && $c['stage'] === 'file deletion' && isset($c['exception'])
        ));
        (new OrphanImageCleaner($resource, $references, $files, $driver, $index, new GalleryWriteLocks($locks), $logger))
            ->schedule(23, ['catalog/product/photo.jpg']);
    }
}
