<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\Http;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\Http\DownloadSourcePolicy;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DownloadSourcePolicyTest extends TestCase
{
    #[DataProvider('allowedUrls')]
    public function testAllowsConfiguredOrigin(string $url): void
    {
        $this->policy()->authorize($url);
        $this->addToAssertionCount(1);
    }

    /** @return list<array{string}> */
    public static function allowedUrls(): array
    {
        return [
            ['https://tenant.test/file.jpg'],
            ['https://tenant.test:443/file.jpg'],
            ['https://TENANT.TEST/file.jpg?signature=abc'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectsUnauthorizedSources(string $url): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy()->authorize($url);
    }

    /** @return list<array{string}> */
    public static function rejectedUrls(): array
    {
        return array_map(static fn (string $url): array => [$url], [
            'https://cdn.example.com/file', 'https://tenant.test.evil.example/file',
            'https://sub.tenant.test/file', 'http://tenant.test/file',
            'https://tenant.test:444/file',
            'https://user:pass@tenant.test/file', 'https://127.0.0.1/file',
            'file:///etc/passwd', "https://tenant.test/\nfile", 'https://tenant.test\\@evil.example/file',
        ]);
    }

    private function policy(): DownloadSourcePolicy
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://tenant.test/api/graphql/');
        return new DownloadSourcePolicy($config);
    }
}
