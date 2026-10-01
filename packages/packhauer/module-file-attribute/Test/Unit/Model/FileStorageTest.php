<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Test\Unit\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PackHauer\FileAttribute\Model\FileStorage;

class FileStorageTest extends TestCase
{
    #[DataProvider('unsafePathProvider')]
    public function testRejectsPathsOutsideMediaStorage(string $path): void
    {
        $this->expectException(LocalizedException::class);
        $this->storage($this->createStub(WriteInterface::class))->normalizePath($path);
    }

    /** @return array<string, array{string}> */
    public static function unsafePathProvider(): array
    {
        return [
            'absolute' => ['/tmp/manual.pdf'],
            'parent traversal' => ['catalog/product/../secret.pdf'],
            'current traversal' => ['catalog/./manual.pdf'],
            'URL' => ['https://example.com/manual.pdf'],
            'Windows separator' => ['catalog\\product\\manual.pdf'],
        ];
    }

    public function testPromotesTemporaryFileAndPreservesDispersion(): void
    {
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('isFile')
            ->with('catalog/product/tmp/files/a/b/manual.pdf')
            ->willReturn(true);
        $directory->method('isExist')
            ->with('catalog/product/files/manual/a/b/manual.pdf')
            ->willReturn(false);
        $directory->expects(self::once())->method('create')->with('catalog/product/files/manual/a/b');
        $directory->expects(self::once())->method('renameFile')->with(
            'catalog/product/tmp/files/a/b/manual.pdf',
            'catalog/product/files/manual/a/b/manual.pdf'
        );

        self::assertSame(
            'catalog/product/files/manual/a/b/manual.pdf',
            $this->storage($directory)->promoteTemporaryFile(
                'catalog/product/tmp/files/a/b/manual.pdf',
                'manual'
            )
        );
    }

    public function testBuildsAnAttributeOwnedPermanentDirectory(): void
    {
        self::assertSame(
            'catalog/product/files/safety_sheet',
            $this->storage($this->createStub(WriteInterface::class))
                ->getPermanentDirectory('safety_sheet')
        );
    }

    public function testRejectsUnsafeAttributeCode(): void
    {
        $this->expectException(LocalizedException::class);
        $this->storage($this->createStub(WriteInterface::class))
            ->getPermanentDirectory('../manual');
    }

    private function storage(WriteInterface $directory): FileStorage
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($directory);

        return new FileStorage($filesystem);
    }
}
