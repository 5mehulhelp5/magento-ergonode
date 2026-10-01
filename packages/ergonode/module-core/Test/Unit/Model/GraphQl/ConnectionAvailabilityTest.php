<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\GraphQl\MutationGuard;
use Ergonode\Core\Model\GraphQl\ReadOnlyGuard;
use Ergonode\Core\Test\Unit\Support\ConnectionTestCase;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TypeError;

class ConnectionAvailabilityTest extends ConnectionTestCase
{
    public function testValidConnectionUsesTheSharedTransportAndQuota(): void
    {
        self::assertTrue($this->connectionClient('valid', 1)->isConnectionAvailable());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRequests(): iterable
    {
        foreach (['empty-url', 'whitespace-url', 'malformed-url', 'non-http-url', 'empty-key', 'whitespace-key',
            '401', '403', '404', 'html'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    #[DataProvider('invalidRequests')]
    public function testDomainQueryReportsConnectionRejectionWithoutDuplicatingLogs(string $scenario): void
    {
        $client = $this->connectionClient($scenario, 1);
        $this->expectException(ConnectionConfigurationException::class);
        $client->query('query ConnectionTest { __typename }');
    }

    public function testProbeDoesNotRetainARejectedKey(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::exactly(2))->method('post')->with('https://ergonode.invalid/api/graphql/');
        $curl->method('getStatus')->willReturnOnConsecutiveCalls(401, 200);
        $curl->method('getBody')->willReturn('{"data":{"__typename":"Query"}}');
        $client = $this->client($curl, $this->silentLogger());
        self::assertFalse($client->isConnectionAvailable());
        self::assertTrue($client->isConnectionAvailable());
    }

    public function testProbeDoesNotHideProgrammingErrors(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('post')->willThrowException(new TypeError('Unexpected programming error'));
        $this->expectException(TypeError::class);
        $this->client($curl, $this->silentLogger())->isConnectionAvailable();
    }

    public function testProbeDoesNotHideStorageFailuresInTheRateLimiter(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::never())->method('post');
        $limiter = $this->createStub(GraphQlRequestLimiterInterface::class);
        $limiter->method('throttle')->willThrowException(new RuntimeException('Database unavailable'));
        $this->expectException(RuntimeException::class);
        $this->client($curl, $this->silentLogger(), $limiter)->isConnectionAvailable();
    }

    public function testTransportFailureDuringDomainWorkStillRemainsVisible(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('post')->willThrowException(new RuntimeException('Connection lost'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->expectException(GraphQlRequestException::class);
        $this->client($curl, $logger)->query('query Product { __typename }');
    }

    private function client(
        Curl $curl,
        LoggerInterface $logger,
        ?GraphQlRequestLimiterInterface $limiter = null
    ): Client {
        return new Client(
            $curl,
            new Json(),
            $this->connectionConfig('valid'),
            new ReadOnlyGuard(),
            $this->createStub(MutationGuard::class),
            $limiter ?? $this->createStub(GraphQlRequestLimiterInterface::class),
            $logger
        );
    }
}
