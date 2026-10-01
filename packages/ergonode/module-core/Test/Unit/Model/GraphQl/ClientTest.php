<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\GraphQl\MutationGuard;
use Ergonode\Core\Model\GraphQl\RateLimiter;
use Ergonode\Core\Model\GraphQl\ReadOnlyGuard;

class ClientTest extends TestCase
{
    public function testRepeatedQueriesAlwaysUseFreshRemoteResponses(): void
    {
        $document = 'query Languages { languageList }';
        $config = $this->createStub(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
        $config->method('getApiKey')->willReturn('read-only-key');
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::exactly(2))->method('post');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturnOnConsecutiveCalls(
            '{"data":{"languageList":["pl_PL"]}}',
            '{"data":{"languageList":["pl_PL","en_GB"]}}'
        );
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects(self::exactly(2))->method('throttle');
        $client = new Client(
            $curl,
            new Json(),
            $config,
            $this->createStub(ReadOnlyGuard::class),
            $this->createStub(MutationGuard::class),
            $rateLimiter,
            $this->createStub(LoggerInterface::class)
        );

        self::assertSame(['languageList' => ['pl_PL']], $client->query($document));
        self::assertSame(['languageList' => ['pl_PL', 'en_GB']], $client->query($document));
    }

    public function testQueryKeepsUsingReadOnlyApiKey(): void
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example');
        $config->expects(self::once())->method('getApiKey')->willReturn('read-only-key');
        $readOnlyGuard = $this->createMock(ReadOnlyGuard::class);
        $readOnlyGuard->expects(self::once())->method('assertQuery')->with('query Languages { languageList }');
        $mutationGuard = $this->createMock(MutationGuard::class);
        $mutationGuard->expects(self::never())->method('authorize');
        $headers = [];

        $client = $this->client($config, $readOnlyGuard, $mutationGuard, $headers);

        self::assertSame(['languageList' => []], $client->query('query Languages { languageList }'));
        self::assertSame('read-only-key', $headers['X-API-KEY']);
    }

    public function testWriteScopeQueryUsesDedicatedUpdateApiKey(): void
    {
        $document = 'query Product { product(sku: "SKU-1") { sku } }';
        $config = $this->createMock(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
        $config->expects(self::never())->method('getApiKey');
        $readOnlyGuard = $this->createMock(ReadOnlyGuard::class);
        $readOnlyGuard->expects(self::once())->method('assertQuery')->with($document);
        $mutationGuard = $this->createMock(MutationGuard::class);
        $mutationGuard->expects(self::once())
            ->method('authorizeUpdateScope')
            ->willReturn('dedicated-update-key');
        $headers = [];

        $result = $this->client($config, $readOnlyGuard, $mutationGuard, $headers)
            ->queryWriteScope($document);

        self::assertSame(['createAttribute' => ['id' => 'attribute-id']], $result);
        self::assertSame('dedicated-update-key', $headers['X-API-KEY']);
    }

    public function testRawMutationPreservesPartialDataAndErrors(): void
    {
        $document = 'mutation Publish { first: publish { id } second: publish { id } }';
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
        $readOnlyGuard = $this->createStub(ReadOnlyGuard::class);
        $mutationGuard = $this->createMock(MutationGuard::class);
        $mutationGuard->expects(self::once())->method('authorize')->with($document)->willReturn('update-key');
        $curl = $this->createStub(Curl::class);
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn((new Json())->serialize([
            'data' => ['first' => ['id' => 'one'], 'second' => null],
            'errors' => [['message' => 'Invalid input.', 'path' => ['second']]],
        ]));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects(self::once())->method('throttle');
        $client = new Client(
            $curl,
            new Json(),
            $config,
            $readOnlyGuard,
            $mutationGuard,
            $rateLimiter,
            $this->createStub(LoggerInterface::class)
        );

        self::assertSame([
            'data' => ['first' => ['id' => 'one'], 'second' => null],
            'errors' => [['message' => 'Invalid input.', 'path' => ['second']]],
        ], $client->mutateWithResponse($document));
    }

    public function testHttpFailureExposesStatusAndRetryAfter(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
        $mutationGuard = $this->createStub(MutationGuard::class);
        $mutationGuard->method('authorize')->willReturn('update-key');
        $curl = $this->createStub(Curl::class);
        $curl->method('getStatus')->willReturn(429);
        $curl->method('getBody')->willReturn('');
        $curl->method('getHeaders')->willReturn(['Retry-After' => '12']);
        $client = new Client(
            $curl,
            new Json(),
            $config,
            $this->createStub(ReadOnlyGuard::class),
            $mutationGuard,
            $this->createStub(RateLimiter::class),
            $this->createStub(LoggerInterface::class)
        );

        try {
            $client->mutateWithResponse('mutation Publish { publish }');
            self::fail('Expected HTTP failure.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $exception->getFailureType());
            self::assertSame(429, $exception->getHttpStatus());
            self::assertSame(12, $exception->getRetryAfterSeconds());
            self::assertTrue($exception->isSafeToRetry());
        }
    }

    public function testAuthorizationHttpFailuresArePermanent(): void
    {
        foreach ([401, 403] as $status) {
            $config = $this->createStub(ConfigProvider::class);
            $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
            $mutationGuard = $this->createStub(MutationGuard::class);
            $mutationGuard->method('authorize')->willReturn('update-key');
            $curl = $this->createStub(Curl::class);
            $curl->method('getStatus')->willReturn($status);
            $curl->method('getBody')->willReturn('');
            $client = new Client(
                $curl,
                new Json(),
                $config,
                $this->createStub(ReadOnlyGuard::class),
                $mutationGuard,
                $this->createStub(RateLimiter::class),
                $this->createStub(LoggerInterface::class)
            );

            try {
                $client->mutateWithResponse('mutation Publish { publish }');
                self::fail('Expected authorization failure.');
            } catch (GraphQlRequestException $exception) {
                self::assertSame(GraphQlRequestException::FAILURE_AUTHORIZATION, $exception->getFailureType());
                self::assertSame($status, $exception->getHttpStatus());
                self::assertFalse($exception->isTransient());
                self::assertFalse($exception->isAmbiguous());
            }
        }
    }

    public function testSerializationFailureOccursBeforeTransportAndIsPermanent(): void
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
        $mutationGuard = $this->createStub(MutationGuard::class);
        $mutationGuard->method('authorize')->willReturn('update-key');
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::never())->method('post');
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects(self::never())->method('throttle');
        $client = new Client(
            $curl,
            new Json(),
            $config,
            $this->createStub(ReadOnlyGuard::class),
            $mutationGuard,
            $rateLimiter,
            $this->createStub(LoggerInterface::class)
        );
        $resource = fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $client->mutateWithResponse('mutation Publish { publish }', ['input' => $resource]);
            self::fail('Expected request construction failure.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame(
                GraphQlRequestException::FAILURE_REQUEST_CONSTRUCTION,
                $exception->getFailureType()
            );
            self::assertFalse($exception->isTransient());
            self::assertFalse($exception->isAmbiguous());
        } finally {
            fclose($resource);
        }
    }

    public function testLocalRateLimitPreventsHttpForReadsAndWrites(): void
    {
        foreach (['query', 'queryWriteScope', 'mutateWithResponse'] as $method) {
            $config = $this->createStub(ConfigProvider::class);
            $config->method('isEnabled')->willReturn(true);
            $config->method('getGraphQlUrl')->willReturn('https://ergonode.example/api/graphql/');
            $config->method('getApiKey')->willReturn('read-key');
            $guard = $this->createStub(MutationGuard::class);
            $guard->method('authorize')->willReturn('write-key');
            $guard->method('authorizeUpdateScope')->willReturn('write-key');
            $curl = $this->createMock(Curl::class);
            $curl->expects(self::never())->method('post');
            $failure = new GraphQlRequestException(
                'Too Many Requests — internal Magento limit.',
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                23
            );
            $limiter = $this->createMock(RateLimiter::class);
            $limiter->expects(self::once())->method('throttle')->willThrowException($failure);
            $client = new Client(
                $curl,
                new Json(),
                $config,
                $this->createStub(ReadOnlyGuard::class),
                $guard,
                $limiter,
                $this->createStub(LoggerInterface::class)
            );
            try {
                $client->{$method}('query Example { __typename }');
                self::fail('Expected the rate limit before HTTP.');
            } catch (GraphQlRequestException $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function client(
        ConfigProvider $config,
        ReadOnlyGuard $readOnlyGuard,
        MutationGuard $mutationGuard,
        array &$headers
    ): Client {
        $curl = $this->createMock(Curl::class);
        $curl->method('addHeader')
            ->willReturnCallback(static function (string $name, string $value) use (&$headers): void {
                $headers[$name] = $value;
            });
        $curl->expects(self::once())->method('post');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')
            ->willReturnCallback(static function () use (&$headers): string {
                return (new Json())->serialize([
                    'data' => str_contains((string)($headers['X-API-KEY'] ?? ''), 'update')
                        ? ['createAttribute' => ['id' => 'attribute-id']]
                        : ['languageList' => []],
                ]);
            });
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects(self::once())->method('throttle');

        return new Client(
            $curl,
            new Json(),
            $config,
            $readOnlyGuard,
            $mutationGuard,
            $rateLimiter,
            $this->createStub(LoggerInterface::class)
        );
    }
}
