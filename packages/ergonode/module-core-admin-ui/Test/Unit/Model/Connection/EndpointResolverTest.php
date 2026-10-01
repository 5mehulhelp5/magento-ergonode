<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Connection;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ergonode\CoreAdminUi\Model\Connection\EndpointResolver;

class EndpointResolverTest extends TestCase
{
    #[DataProvider('validUrlProvider')]
    public function testResolvesValidErgonodeUrl(string $url, string $expected): void
    {
        self::assertSame($expected, (new EndpointResolver())->resolveGraphQlUrl($url));
    }

    /** @return array<string, array{string, string}> */
    public static function validUrlProvider(): array
    {
        return [
            'single-character tenant' => [
                'https://a.ergonode.cloud',
                'https://a.ergonode.cloud/api/graphql/',
            ],
            'tenant with a hyphen' => [
                'https://example-shop.ergonode.cloud',
                'https://example-shop.ergonode.cloud/api/graphql/',
            ],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function testRejectsInvalidErgonodeUrl(string $url): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode URL must match https://{tenant}.ergonode.cloud.');

        (new EndpointResolver())->resolveGraphQlUrl($url);
    }

    /** @return array<string, array{string}> */
    public static function invalidUrlProvider(): array
    {
        return [
            'HTTP scheme' => ['http://example.ergonode.cloud'],
            'FTP scheme' => ['ftp://example.ergonode.cloud'],
            'missing tenant' => ['https://.ergonode.cloud'],
            'different domain' => ['https://ergonode.example'],
            'trailing slash' => ['https://example.ergonode.cloud/'],
            'GraphQL path' => ['https://example.ergonode.cloud/api/graphql/'],
            'query string' => ['https://example.ergonode.cloud?foo=bar'],
            'invalid tenant label' => ['https://example_shop.ergonode.cloud'],
        ];
    }

    public function testRejectsEmptyErgonodeUrlAsRequired(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode URL is required.');

        (new EndpointResolver())->resolveGraphQlUrl('');
    }
}
