<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\Rest;

use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Publisher\Api\Exception\RestRequestException;
use Ergonode\Publisher\Model\Rest\Transport;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class TransportTest extends TestCase
{
    #[DataProvider('failureStatuses')]
    public function testHttpFailuresLogOnlySafeContextAndKeepStatus(int $status, ?int $retry): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getHeaders')->willReturn(['Retry-After' => '7']);
        $curl->method('getBody')->willReturn('private response token');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Ergonode REST request failed.', [
            'profile' => 'test', 'method' => 'POST', 'path' => '/api/v1/pl_PL/attributes/grid',
            'http_status' => $status,
        ]);
        $transport = new Transport(
            $curl,
            new Json(),
            $this->createStub(GraphQlRequestLimiterInterface::class),
            $logger
        );
        try {
            $transport->request(
                'test',
                'https://example.test',
                'POST',
                '/api/v1/pl_PL/attributes/grid?secret=private-query#private-fragment',
                ['password' => 'private-password', 'refresh_token' => 'private-refresh'],
                'private-access'
            );
            self::fail('An HTTP failure must not return a successful response.');
        } catch (RestRequestException $exception) {
            self::assertSame($status, $exception->getStatusCode());
            self::assertSame($retry, $exception->getRetryAfterSeconds());
            if ($status === 400) {
                self::assertStringContainsString('request parameters', $exception->getMessage());
                self::assertStringContainsString('integration request format', $exception->getMessage());
                self::assertStringContainsString('Magento log', $exception->getMessage());
            }
            if ($status === 405) {
                self::assertStringContainsString('request method', $exception->getMessage());
                self::assertStringContainsString('Magento log', $exception->getMessage());
            }
        }
    }

    public function testNetworkFailureDoesNotLogExceptionContainingCredentials(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->method('get')->willThrowException(new RuntimeException('private-password private-token'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Ergonode REST request failed.', [
            'profile' => 'test', 'method' => 'GET', 'path' => '/api/v1/resource', 'http_status' => null,
        ]);
        $transport = new Transport(
            $curl,
            new Json(),
            $this->createStub(GraphQlRequestLimiterInterface::class),
            $logger
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to connect to Ergonode REST. Retry the operation.');
        $transport->request('test', 'https://example.test', 'GET', '/api/v1/resource?secret=private');
    }

    public function testSuccessfulPostUsesJsonBodyAndDoesNotLogFailure(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())->method('post')->with(
            'https://example.test/api/v1/pl_PL/attributes/grid',
            '{"offset":0,"limit":100}'
        );
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('{"collection":[]}');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $transport = new Transport(
            $curl,
            new Json(),
            $this->createStub(GraphQlRequestLimiterInterface::class),
            $logger
        );
        self::assertSame(['collection' => []], $transport->request(
            'test',
            'https://example.test',
            'POST',
            '/api/v1/pl_PL/attributes/grid',
            ['offset' => 0, 'limit' => 100]
        ));
    }

    /** @return array<string, array{int, ?int}> */
    public static function failureStatuses(): array
    {
        return [
            'parameters' => [400, null], 'method' => [405, null],
            'forbidden' => [403, null], 'rate limit' => [429, 7],
        ];
    }
}
