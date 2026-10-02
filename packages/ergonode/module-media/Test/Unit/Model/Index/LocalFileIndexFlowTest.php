<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Index;

use Magento\Framework\Filesystem\Driver\File;
use Ergonode\Media\Model\Port\ScanStateInterface;
use Ergonode\Media\Model\Index\IndexedFileResolver;
use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Index\LocalFileScanner;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class LocalFileIndexFlowTest extends TestCase
{
    private string $root;
    private LocalFiles $files;
    private LocalFileIndexInterface $index;
    /** @var array<string, array{path:string,content_hash:string,size:int,modified_at:int}> */
    private array $rows = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ergonode-index-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/catalog/product/cache', 0777, true);
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturn($this->root);
        $this->files = new LocalFiles($directories, new File());
        $index = $this->createStub(LocalFileIndexInterface::class);
        $index->method('get')->willReturnCallback(fn (string $path): ?array => $this->rows[$path] ?? null);
        $index->method('save')->willReturnCallback(function (string $path, string $hash, int $size, int $mtime): void {
            $this->rows[$path] = ['path' => $path, 'content_hash' => $hash, 'size' => $size, 'modified_at' => $mtime];
        });
        $index->method('find')->willReturnCallback(fn (string $hash): array => array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => hash_equals($row['content_hash'], $hash)
        )));
        $index->method('remove')->willReturnCallback(function (string $path): void {
            unset($this->rows[$path]);
        });
        $index->method('page')->willReturnCallback(function (string $after, int $limit): array {
            ksort($this->rows);
            return array_slice(array_values(array_filter(
                $this->rows,
                static fn (array $row): bool => strcmp($row['path'], $after) > 0
            )), 0, $limit);
        });
        $this->index = $index;
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testScanFindsDuplicatesWithoutChangingFilesAndSkipsCache(): void
    {
        file_put_contents($this->root . '/catalog/product/one.jpg', 'same image');
        file_put_contents($this->root . '/catalog/product/two.jpg', 'same image');
        file_put_contents($this->root . '/catalog/product/cache/resized.jpg', 'resized');
        file_put_contents($this->root . '/catalog/product/.ergonode-pending.tmp', 'partial');
        $first = $this->scanner()->scan();
        self::assertSame(['indexed' => 2, 'reused' => 0, 'removed' => 0], $first);
        self::assertCount(2, $this->rows);
        self::assertSame('catalog/product/one.jpg', (new IndexedFileResolver($this->index, $this->files))
            ->find(hash('sha256', 'same image', true)));
        self::assertSame(['indexed' => 0, 'reused' => 2, 'removed' => 0], $this->scanner()->scan());
        self::assertSame('same image', file_get_contents($this->root . '/catalog/product/two.jpg'));
    }

    public function testResolverRejectsAnIndexedFileChangedEvenWithSameSizeAndTimestamp(): void
    {
        $path = 'catalog/product/photo.jpg';
        file_put_contents($this->root . '/' . $path, 'first');
        $this->scanner()->scan();
        $mtime = $this->rows[$path]['modified_at'];
        file_put_contents($this->root . '/' . $path, 'other');
        touch($this->root . '/' . $path, $mtime);
        self::assertNull((new IndexedFileResolver($this->index, $this->files))->find(hash('sha256', 'first', true)));
        self::assertSame(hash('sha256', 'other', true), $this->rows[$path]['content_hash']);
    }

    public function testNewImportedFileIsAvailableWithoutAnotherFullScan(): void
    {
        $path = 'catalog/product/new.jpg';
        $hash = hash('sha256', 'new', true);
        file_put_contents($this->root . '/' . $path, 'new');
        $resolver = new IndexedFileResolver($this->index, $this->files);
        $resolver->remember($path, $hash);
        self::assertSame($path, $resolver->find($hash));
        unlink($this->root . '/' . $path);
        self::assertNull($resolver->find($hash));
        self::assertSame([], $this->rows);
    }

    public function testScanRemovesMissingIndexEntriesOnly(): void
    {
        $path = 'catalog/product/removed.jpg';
        file_put_contents($this->root . '/' . $path, 'old');
        $this->scanner()->scan();
        unlink($this->root . '/' . $path);
        self::assertSame(['indexed' => 0, 'reused' => 0, 'removed' => 1], $this->scanner()->scan());
        self::assertSame([], $this->rows);
    }

    private function scanner(): LocalFileScanner
    {
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);

        return new LocalFileScanner(
            $this->createStub(QueuePublisher::class),
            $this->files,
            $this->index,
            $locks,
            $this->createStub(ScanStateInterface::class)
        );
    }
}
