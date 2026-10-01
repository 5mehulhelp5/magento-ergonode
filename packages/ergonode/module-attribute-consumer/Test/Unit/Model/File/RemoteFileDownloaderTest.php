<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\File;

use Ergonode\AttributeConsumer\Model\File\RemoteFileDownloader;
use Ergonode\Core\Api\DownloadSourcePolicyInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Http\DownloadSourcePolicy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RemoteFileDownloaderTest extends TestCase
{
    public function testSameOriginRedirectIsReauthorizedWithoutKeepingResponseBody(): void
    {
        $policy = $this->createMock(DownloadSourcePolicyInterface::class);
        $seen = [];
        $policy->expects(self::exactly(2))->method('authorize')->willReturnCallback(
            static function (string $url) use (&$seen): void {
                $seen[] = $url;
            }
        );
        $first = $this->client(302, 'redirect body', ['Location' => '/files/a.jpg']);
        $first->expects(self::once())->method('addHeader')->with('X-API-KEY', 'secret');
        $second = $this->client(200, 'actual file');
        $second->expects(self::once())->method('addHeader')->with('X-API-KEY', 'secret');
        $factory = $this->createMock(CurlFactory::class);
        $factory->expects(self::exactly(2))->method('create')->willReturnOnConsecutiveCalls($first, $second);
        $config = $this->createMock(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->expects(self::exactly(2))->method('getApiKey')->willReturn('secret');
        $path = tempnam(sys_get_temp_dir(), 'ergonode-test-');
        try {
            (new RemoteFileDownloader($factory, $policy, $config, new File()))
                ->download('https://tenant.test/a.jpg', $path);
            self::assertSame('actual file', file_get_contents($path));
            self::assertSame(['https://tenant.test/a.jpg', 'https://tenant.test/files/a.jpg'], $seen);
        } finally {
            unlink($path);
        }
    }

    public function testExternalRedirectNeverCreatesSecondClient(): void
    {
        $first = $this->client(302, 'redirect', ['Location' => 'https://cdn.example.com/file']);
        $first->expects(self::once())->method('addHeader')->with('X-API-KEY', 'secret');
        $factory = $this->createMock(CurlFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($first);
        $config = $this->createMock(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getGraphQlUrl')->willReturn('https://tenant.test/api/graphql/');
        $config->expects(self::once())->method('getApiKey')->willReturn('secret');
        $policy = new DownloadSourcePolicy($config);
        $path = tempnam(sys_get_temp_dir(), 'ergonode-test-');
        try {
            $this->expectException(LocalizedException::class);
            (new RemoteFileDownloader($factory, $policy, $config, new File()))
                ->download('https://tenant.test/file', $path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<string, string> $headers
     * @return Curl&MockObject
     */
    private function client(int $status, string $body, array $headers = []): Curl
    {
        $client = $this->createMock(Curl::class);
        $writer = null;
        $client->method('setOptions')->willReturnCallback(static function (array $options) use (&$writer): void {
            self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
            $writer = $options[CURLOPT_WRITEFUNCTION];
        });
        $client->method('get')->willReturnCallback(static function () use (&$writer, $body): void {
            self::assertSame(strlen($body), $writer(null, $body));
        });
        $client->method('getStatus')->willReturn($status);
        $client->method('getHeaders')->willReturn($headers);
        $client->expects(self::never())->method('getBody');
        return $client;
    }
}
