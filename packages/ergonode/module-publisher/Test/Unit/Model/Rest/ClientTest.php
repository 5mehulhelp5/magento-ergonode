<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\Rest;

use Ergonode\Publisher\Api\Exception\RestRequestException;
use Ergonode\Publisher\Model\Rest\AccessTokenProvider;
use Ergonode\Publisher\Model\Rest\Client;
use Ergonode\Publisher\Model\Rest\ConnectionContext;
use Ergonode\Publisher\Model\Rest\Transport;
use Magento\Framework\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClientTest extends TestCase
{
    public function testUnauthorizedRequestRefreshesOnceAndRetriesWithNewToken(): void
    {
        $tokens = $this->createMock(AccessTokenProvider::class);
        $tokens->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $profile, string $origin, ?string $rejected = null): array {
                self::assertSame('test', $profile);
                self::assertSame('https://example.test', $origin);
                return ['token' => $rejected === null ? 'old' : 'new', 'generation' => 'one'];
            }
        );
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::exactly(2))->method('request')->willReturnCallback(
            static function ($profile, $origin, $method, $path, $payload, $token): array {
                if ($token === 'old') {
                    throw new RestRequestException(401);
                }
                self::assertSame('new', $token);
                return ['result' => true];
            }
        );
        self::assertSame(
            ['result' => true],
            (new Client($this->context(), $tokens, $transport, $this->createStub(LoggerInterface::class)))
                ->request('GET', '/api/test')
        );
    }

    public function testForbiddenRequestDoesNotRefreshOrRetry(): void
    {
        $tokens = $this->createMock(AccessTokenProvider::class);
        $tokens->expects(self::once())->method('get')->willReturn(['token' => 'valid', 'generation' => 'one']);
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::once())->method('request')->willThrowException(new RestRequestException(403));
        $this->expectException(RestRequestException::class);
        (new Client($this->context(), $tokens, $transport, $this->createStub(LoggerInterface::class)))
            ->request('POST', '/api/test', ['value' => 'test']);
    }

    public function testRenewedTokenRejectionLogsTerminalAuthenticationFailure(): void
    {
        $tokens = $this->createMock(AccessTokenProvider::class);
        $tokens->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            ['token' => 'old', 'generation' => 'one'],
            ['token' => 'new', 'generation' => 'one']
        );
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::exactly(2))->method('request')
            ->willThrowException(new RestRequestException(401));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Ergonode REST authentication failed.',
            ['profile' => 'test', 'reason' => 'renewed_token_rejected', 'http_status' => 401]
        );

        $this->expectException(AuthenticationException::class);
        (new Client($this->context(), $tokens, $transport, $logger))->request('GET', '/api/test');
    }

    private function context(): ConnectionContext
    {
        $context = $this->createStub(ConnectionContext::class);
        $context->method('profile')->willReturn('test');
        $context->method('origin')->willReturn('https://example.test');
        return $context;
    }
}
