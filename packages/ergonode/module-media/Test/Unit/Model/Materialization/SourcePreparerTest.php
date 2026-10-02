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

    public static function revisions(): array
    {
        return ['unchanged' => [2], 'stream advanced during activation' => [3]];
    }
}
