<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\File;

use Ergonode\AttributeConsumer\Model\File\FileDownloader;
use Ergonode\AttributeConsumer\Model\File\RemoteFileDownloader;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FileDownloaderTest extends TestCase
{
    public function testFailedTransferLeavesNoFinalFileOrTemporaryFile(): void
    {
        $directory = sys_get_temp_dir() . '/ergonode-file-' . bin2hex(random_bytes(8));
        $files = new File();
        $files->mkdir($directory);
        $remote = $this->createMock(RemoteFileDownloader::class);
        $temporary = null;
        $remote->expects(self::once())->method('download')->willReturnCallback(
            static function (string $url, string $path) use (&$temporary): void {
                self::assertSame('https://cdn.example.com/image.jpg', $url);
                $temporary = $path;
                file_put_contents($path, 'partial content');
                throw new LocalizedException(__('Transfer interrupted.'));
            }
        );
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/media/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $downloader = new FileDownloader(
            $remote,
            $files,
            new DirectoryList($directory, [DirectoryList::MEDIA => ['path' => $directory]]),
            $this->createStub(ConfigProvider::class),
            $stores
        );
        try {
            $relative = $downloader->sourceToRelativePath('https://cdn.example.com/image.jpg');
            try {
                $downloader->download('https://cdn.example.com/image.jpg');
                self::fail('Expected the transfer to fail.');
            } catch (LocalizedException $exception) {
                self::assertSame('Transfer interrupted.', $exception->getMessage());
            }
            self::assertNotNull($temporary);
            self::assertFileDoesNotExist($temporary);
            self::assertFileDoesNotExist($directory . '/' . $relative);
        } finally {
            $files->rmdir($directory, true);
        }
    }
}
