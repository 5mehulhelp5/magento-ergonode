<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Test\Unit\Model\Connection;

use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Ergonode\CoreAdminUi\Model\Connection\ConnectionTester;
use Ergonode\CoreAdminUi\Model\Connection\EndpointResolver;

class ConnectionTesterTest extends TestCase
{
    public function testSendsSideEffectFreeGraphQlQueryWithSubmittedCredentials(): void
    {
        $headers = [];
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())->method('setHeaders')->willReturnCallback(
            static function (array $value) use (&$headers): void {
                $headers = $value;
            }
        );
        $curl->expects(self::once())->method('post')->with(
            'https://example.ergonode.cloud/api/graphql/',
            '{"query":"query ConnectionTest { __typename }"}'
        );
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('{"data":{"__typename":"Query"}}');

        (new ConnectionTester(
            $curl,
            new Json(),
            new EndpointResolver(),
            $this->createStub(GraphQlRequestLimiterInterface::class)
        ))->test(
            'https://example.ergonode.cloud',
            'api-key',
            'production'
        );

        self::assertSame('api-key', $headers['X-API-KEY']);
    }

    public function testRejectsEmptyApiKeyBeforeSendingRequest(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::never())->method('post');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('API Key is required to test the connection.');

        (new ConnectionTester(
            $curl,
            new Json(),
            new EndpointResolver(),
            $this->createStub(GraphQlRequestLimiterInterface::class)
        ))->test(
            'https://example.ergonode.cloud',
            '',
            'production'
        );
    }

    public function testReportsRejectedCredentials(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn(401);
        $curl->expects(self::once())->method('post');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode connection failed with HTTP status 401.');

        (new ConnectionTester(
            $curl,
            new Json(),
            new EndpointResolver(),
            $this->createStub(GraphQlRequestLimiterInterface::class)
        ))->test(
            'https://example.ergonode.cloud',
            'invalid-key',
            'production'
        );
    }

    public function testRejectsGraphQlErrorResponse(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('{"errors":[{"message":"Unauthorized"}]}');
        $curl->expects(self::once())->method('post');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode rejected the connection test.');

        (new ConnectionTester(
            $curl,
            new Json(),
            new EndpointResolver(),
            $this->createStub(GraphQlRequestLimiterInterface::class)
        ))->test(
            'https://example.ergonode.cloud',
            'invalid-key',
            'production'
        );
    }
    public function testLocalRateLimitIsPropagatedBeforeSendingRequest(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::never())->method('post');
        $failure = new GraphQlRequestException(
            'Too Many Requests — internal Magento limit.',
            GraphQlRequestException::FAILURE_RATE_LIMIT,
            429,
            23
        );
        $limiter = $this->createMock(GraphQlRequestLimiterInterface::class);
        $limiter->expects(self::once())->method('throttle')->with('production')->willThrowException($failure);
        try {
            (new ConnectionTester($curl, new Json(), new EndpointResolver(), $limiter))->test(
                'https://example.ergonode.cloud',
                'api-key',
                'production'
            );
            self::fail('Expected the local rate limit to prevent transport.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame($failure, $exception);
        }
    }
}
