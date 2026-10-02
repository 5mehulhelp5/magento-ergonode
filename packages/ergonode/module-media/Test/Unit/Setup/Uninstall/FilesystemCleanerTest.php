<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Setup\Uninstall;

use Ergonode\Media\Setup\Uninstall\FilesystemCleaner;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use PHPUnit\Framework\TestCase;

class FilesystemCleanerTest extends TestCase
{
    public function testRemovesOnlyOwnedCacheAndMaterializationDirectories(): void
    {
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturnMap([
            [DirectoryList::VAR_DIR, '/project/var'],
            [DirectoryList::MEDIA, '/project/pub/media'],
        ]);
        $file = $this->createMock(File::class);
        $file->expects(self::exactly(3))->method('fileExists')->withAnyParameters()->willReturn(true);
        $paths = [];
        $file->expects(self::exactly(3))->method('rmdir')
            ->willReturnCallback(static function (string $path, bool $recursive) use (&$paths): bool {
                self::assertTrue($recursive);
                $paths[] = $path;

                return true;
            });

        (new FilesystemCleaner($directories, $file))->execute();

        self::assertSame([
            '/project/var/ergonode/media',
            '/project/pub/media/catalog/product/ergonode/shared',
            '/project/pub/media/catalog/product/ergonode/seo',
        ], $paths);
    }
}
