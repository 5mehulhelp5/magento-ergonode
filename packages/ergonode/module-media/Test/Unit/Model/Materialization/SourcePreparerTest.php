<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Materialization;

use Ergonode\Core\Api\DownloadSourcePolicyInterface;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Download\DownloadGuard;
use Ergonode\Media\Model\GraphQl\MultimediaClient;
use Ergonode\Media\Model\Materialization\SourcePreparer;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourcePreparerTest extends TestCase
{
    #[DataProvider('revisions')]
    public function testDownloadUsesCleanUnauthenticatedClientAndCannotReturnNewRevisionWithOldContent(int $returnedRevision): void
    {
        $url = 'https://example.ergonode.cloud/media/file.png';
        $asset = new Asset(7, 'file.png', $url, 'file', 'png', 'image/png', null, null, 2, 'dirty');
        $ready = new Asset(7, 'file.png', $url, 'file', 'png', 'image/png', hash('sha256', 'bytes', true), 'cache.png', $returnedRevision, 'active');
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->with(7)->willReturnOnConsecutiveCalls($asset, $ready);
        $repository->expects(self::once())->method('activate')->with(7, hash('sha256', 'bytes', true), self::isString(), 5, 2);
        $policy = $this->createMock(DownloadSourcePolicyInterface::class);
        $policy->expects(self::once())->method('authorize')->with($url);
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())->method('setHeaders')->with(['Accept' => '*/*']);
        $curl->expects(self::never())->method('addHeader');
        $curl->expects(self::once())->method('get')->with($url);
        $curl->expects(self::exactly(3))->method('setOption')->willReturnCallback(
            static function ($option, $value): void {
                self::assertSame([
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                ][$option], $value);
            }
        );
        $curl->method('getBody')->willReturn('bytes');
        $curl->method('getStatus')->willReturn(200);
        $factory = $this->createMock(CurlFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($curl);
        $guard = $this->createStub(DownloadGuard::class);
        $guard->method('run')->willReturnCallback(static fn(callable $operation): mixed => $operation());
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturn('/var');
        $file = $this->createStub(File::class);
        $file->method('fileExists')->willReturn(false);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->method('write')->willReturn(5);
        $file->method('mv')->willReturn(true);
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock');
        $preparer = new SourcePreparer($repository, $this->createStub(MultimediaClient::class), $factory,
            $policy, $guard, $directories, $file, $locks);
        if ($returnedRevision !== 2) {
            $this->expectException(LocalizedException::class);
        }
        self::assertSame($ready, $preparer->prepare($asset));
    }

    public function testActivatesOnlyAfterPublishingTheCompleteTemporaryFile(): void
    {
        $asset = $this->asset();
        $ready = $this->readyAsset();
        $published = false;
        $temporary = null;
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturnOnConsecutiveCalls($asset, $ready);
        $repository->expects(self::once())->method('activate')->willReturnCallback(
            static function (int $id, string $hash, string $cache, int $size, int $revision) use (&$published): void {
                self::assertTrue($published);
                self::assertSame(7, $id);
                self::assertSame(hash('sha256', 'bytes', true), $hash);
                self::assertSame(5, $size);
                self::assertSame(2, $revision);
                self::assertStringStartsWith('ergonode/media/', $cache);
            }
        );
        $file = $this->createMock(File::class);
        $file->method('fileExists')->willReturn(false);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->expects(self::once())->method('write')->willReturnCallback(
            static function (string $path, string $body) use (&$temporary): int {
                self::assertStringContainsString('/.ergonode-source-', $path);
                self::assertSame('bytes', $body);
                $temporary = $path;
                return strlen($body);
            }
        );
        $file->expects(self::once())->method('mv')->willReturnCallback(
            static function (string $from, string $to) use (&$temporary, &$published): bool {
                self::assertSame($temporary, $from);
                self::assertSame(dirname($from), dirname($to));
                self::assertStringEndsWith('.png', $to);
                $published = true;
                return true;
            }
        );
        $file->expects(self::never())->method('rm');

        self::assertSame($ready, $this->preparer($repository, $file)->prepare($asset));
    }

    #[DataProvider('writeFailures')]
    public function testFailedOrShortWriteDoesNotPublishOrActivateAndIncludesDiagnosticContext(int|false $written): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturn($this->asset());
        $repository->expects(self::never())->method('activate');
        $file = $this->createMock(File::class);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->method('fileExists')->willReturnCallback(
            static fn (string $path): bool => str_contains($path, '/.ergonode-source-')
        );
        $file->expects(self::once())->method('write')->willReturn($written);
        $file->expects(self::never())->method('mv');
        $file->expects(self::once())->method('rm')->with(self::stringContains('/.ergonode-source-'))->willReturn(true);

        try {
            $this->preparer($repository, $file)->prepare($this->asset());
            self::fail('Incomplete source must not be activated.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('file.png', $exception->getMessage());
            self::assertStringContainsString('asset 7, revision 2', $exception->getMessage());
            self::assertStringContainsString('write temporary file', $exception->getMessage());
            self::assertStringContainsString('Expected 5 bytes, wrote ' . ($written === false ? 'none' : $written), $exception->getMessage());
        }
    }

    public static function writeFailures(): array
    {
        return ['failed write' => [false], 'empty write' => [0], 'short write' => [2]];
    }

    public function testFailedPublicationRemovesTemporaryFileAndDoesNotActivate(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturn($this->asset());
        $repository->expects(self::never())->method('activate');
        $file = $this->createMock(File::class);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->method('fileExists')->willReturnCallback(
            static fn (string $path): bool => str_contains($path, '/.ergonode-source-')
        );
        $file->expects(self::once())->method('write')->willReturn(5);
        $file->expects(self::once())->method('mv')->willReturn(false);
        $file->expects(self::once())->method('rm')->willReturn(true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('publish source cache');

        $this->preparer($repository, $file)->prepare($this->asset());
    }

    #[DataProvider('cleanupFailures')]
    public function testCleanupFailurePreservesTheOriginalWriteFailure(bool $throws): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturn($this->asset());
        $repository->expects(self::never())->method('activate');
        $file = $this->createMock(File::class);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->method('fileExists')->willReturnCallback(
            static fn (string $path): bool => str_contains($path, '/.ergonode-source-')
        );
        $file->expects(self::once())->method('write')->willReturn(2);
        $file->expects(self::never())->method('mv');
        $cleanup = $file->expects(self::once())->method('rm');
        if ($throws) {
            $cleanup->willThrowException(new \RuntimeException('cleanup access denied'));
        } else {
            $cleanup->willReturn(false);
        }
        try {
            $this->preparer($repository, $file)->prepare($this->asset());
            self::fail('Incomplete source must not be activated.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('Expected 5 bytes, wrote 2', $exception->getMessage());
            self::assertStringContainsString(
                $throws ? 'cleanup access denied' : 'Unable to remove temporary source file',
                $exception->getMessage()
            );
            self::assertInstanceOf(LocalizedException::class, $exception->getPrevious());
        }
    }

    public static function cleanupFailures(): array
    {
        return ['cleanup returns false' => [false], 'cleanup throws' => [true]];
    }

    public function testDirectoryFailureIncludesSourceAndStageWithoutWriting(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturn($this->asset());
        $repository->expects(self::never())->method('activate');
        $file = $this->createMock(File::class);
        $file->method('dirname')->willReturnCallback('dirname');
        $file->method('fileExists')->willReturn(false);
        $file->expects(self::once())->method('checkAndCreateFolder')
            ->willThrowException(new \RuntimeException('access denied'));
        $file->expects(self::never())->method('write');
        $file->expects(self::never())->method('mv');
        $file->expects(self::never())->method('rm');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('file.png" (asset 7, revision 2) at stage "create cache directory": access denied');

        $this->preparer($repository, $file)->prepare($this->asset());
    }

    #[DataProvider('filesystemRuns')]
    public function testPublishedCacheContainsExactBytesBeforeActivationWithRealFilesystem(bool $shortWriteFirst): void
    {
        $directory = sys_get_temp_dir() . '/ergonode-source-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('getAsset')->willReturnOnConsecutiveCalls(
            $this->asset(),
            $shortWriteFirst ? $this->asset() : $this->readyAsset(),
            $this->readyAsset()
        );
        $repository->expects(self::once())->method('activate')->willReturnCallback(
            static function (int $id, string $hash, string $cache) use ($directory): void {
                self::assertSame('bytes', file_get_contents($directory . '/' . $cache));
                self::assertSame([], glob(dirname($directory . '/' . $cache) . '/.ergonode-source-*'));
            }
        );
        try {
            if ($shortWriteFirst) {
                $shortWriter = new class extends File {
                    public function write($filename, $src, $mode = null)
                    {
                        return parent::write($filename, substr($src, 0, 2), $mode);
                    }
                };
                try {
                    $this->preparer($repository, $shortWriter, $directory)->prepare($this->asset());
                    self::fail('Partial cache must not be published.');
                } catch (LocalizedException $exception) {
                    self::assertStringContainsString('Expected 5 bytes, wrote 2', $exception->getMessage());
                }
                $remaining = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($remaining as $path) {
                    self::assertFalse($path->isFile(), 'Failed write must leave no published or temporary file.');
                }
            }
            $this->preparer($repository, new File(), $directory)->prepare($this->asset());
        } finally {
            $paths = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($paths as $path) {
                $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
            }
            rmdir($directory);
        }
    }

    public static function filesystemRuns(): array
    {
        return ['first run succeeds' => [false], 'new manual run after short write' => [true]];
    }

    public function testKnownActiveCacheIsReusedWithoutWritingAgain(): void
    {
        $asset = $this->readyAsset();
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects(self::once())->method('getAsset')->with(7)->willReturn($asset);
        $repository->expects(self::never())->method('activate');
        $file = $this->createMock(File::class);
        $file->expects(self::once())->method('fileExists')->with('/var/cache.png')->willReturn(true);
        $file->expects(self::never())->method('write');
        $file->expects(self::never())->method('mv');
        $file->expects(self::never())->method('rm');

        self::assertSame($asset, $this->preparer($repository, $file)->prepare($asset));
    }

    private function preparer(MediaRepository $repository, File $file, string $varDirectory = '/var'): SourcePreparer
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('getBody')->willReturn('bytes');
        $curl->method('getStatus')->willReturn(200);
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturn($curl);
        $guard = $this->createStub(DownloadGuard::class);
        $guard->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturn($varDirectory);
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $locks->expects(self::once())->method('unlock');
        return new SourcePreparer($repository, $this->createStub(MultimediaClient::class), $factory,
            $this->createStub(DownloadSourcePolicyInterface::class), $guard, $directories, $file, $locks);
    }

    private function asset(): Asset
    {
        return new Asset(7, 'file.png', 'https://example.ergonode.cloud/media/file.png',
            'file', 'png', 'image/png', null, null, 2, 'dirty');
    }

    private function readyAsset(): Asset
    {
        return new Asset(7, 'file.png', 'https://example.ergonode.cloud/media/file.png',
            'file', 'png', 'image/png', hash('sha256', 'bytes', true), 'cache.png', 2, 'active');
    }

    public static function revisions(): array
    {
        return ['unchanged' => [2], 'stream advanced during activation' => [3]];
    }
}
