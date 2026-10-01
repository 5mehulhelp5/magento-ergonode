<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\Rest;

use Ergonode\Publisher\Api\Exception\RestRequestException as RequestException;
use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Ergonode\Publisher\Model\Rest\AccessTokenProvider;
use Ergonode\Publisher\Model\Rest\ConnectionLock;
use Ergonode\Publisher\Model\Rest\TokenPair;
use Ergonode\Publisher\Model\Rest\Transport;
use Magento\Framework\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AccessTokenProviderTest extends TestCase
{
    public function testExpiredAccessTokenRefreshesAndSavesBothTokens(): void
    {
        $storage = $this->createMock(ConnectionStorageInterface::class);
        $storage->method('get')->with('test')->willReturn($this->connection());
        $fresh = 'e30.' . rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=') . '.sig';
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::once())->method('request')->with(
            'test',
            'https://example.test',
            'POST',
            '/api/v1/token/refresh',
            ['refresh_token' => 'old-refresh']
        )->willReturn(['token' => $fresh, 'refresh_token' => 'new-refresh']);
        $storage->expects(self::once())->method('save')->with('test', self::callback(
            static fn (array $row): bool => $row['token'] === $fresh && $row['refresh_token'] === 'new-refresh'
                && $row['generation'] === 'login-1'
        ));
        $result = $this->provider($storage, $transport)->get('test', 'https://example.test');
        self::assertSame($fresh, $result['token']);
    }

    public function testAnotherRequestAlreadyRefreshedWhileWaitingForLock(): void
    {
        $old = $this->connection();
        $new = array_replace($old, ['token' => 'renewed', 'expires_at' => time() + 3600]);
        $storage = $this->createMock(ConnectionStorageInterface::class);
        $storage->method('get')->willReturnOnConsecutiveCalls($old, $new);
        $storage->expects(self::never())->method('save');
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::never())->method('request');
        self::assertSame(
            'renewed',
            $this->provider($storage, $transport)->get('test', 'https://example.test')['token']
        );
    }

    public function testRejectedRefreshRemovesConnection(): void
    {
        $storage = $this->createMock(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn($this->connection());
        $storage->expects(self::once())->method('delete')->with('test');
        $transport = $this->createStub(Transport::class);
        $transport->method('request')->willThrowException(new RequestException(401));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Ergonode REST authentication failed.',
            ['profile' => 'test', 'reason' => 'refresh_rejected', 'http_status' => 401]
        );
        $this->expectException(AuthenticationException::class);
        $this->provider($storage, $transport, $logger)->get('test', 'https://example.test');
    }

    public function testTransientRefreshFailurePreservesConnection(): void
    {
        $storage = $this->createMock(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn($this->connection());
        $storage->expects(self::never())->method('delete');
        $transport = $this->createStub(Transport::class);
        $transport->method('request')->willThrowException(new RequestException(503));
        $this->expectException(RequestException::class);
        $this->provider($storage, $transport)->get('test', 'https://example.test');
    }

    public function testDifferentOriginNeverReceivesStoredCredentials(): void
    {
        $storage = $this->createStub(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn($this->connection());
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::never())->method('request');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Ergonode REST authentication failed.',
            ['profile' => 'test', 'reason' => 'origin_mismatch']
        );
        $this->expectException(AuthenticationException::class);
        $this->provider($storage, $transport, $logger)->get('test', 'https://another.test');
    }

    public function testMissingConnectionLogsReasonWithoutRequest(): void
    {
        $storage = $this->createStub(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn(null);
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::never())->method('request');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Ergonode REST authentication failed.',
            ['profile' => 'test', 'reason' => 'connection_missing']
        );

        $this->expectException(AuthenticationException::class);
        $this->provider($storage, $transport, $logger)->get('test', 'https://example.test');
    }

    public function testMissingRefreshTokenLogsReasonWithoutLeakingAccessToken(): void
    {
        $storage = $this->createStub(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn(array_replace($this->connection(), ['refresh_token' => '']));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Ergonode REST authentication failed.',
            ['profile' => 'test', 'reason' => 'refresh_token_missing']
        );

        $this->expectException(AuthenticationException::class);
        $this->provider($storage, $this->createStub(Transport::class), $logger)
            ->get('test', 'https://example.test');
    }

    private function provider(
        ConnectionStorageInterface $storage,
        Transport $transport,
        ?LoggerInterface $logger = null
    ): AccessTokenProvider {
        $lock = $this->createStub(ConnectionLock::class);
        $lock->method('execute')->willReturnCallback(
            static fn (string $profile, callable $operation): mixed => $operation()
        );
        return new AccessTokenProvider(
            $storage,
            $lock,
            $transport,
            new TokenPair(),
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    /** @return array<string, mixed> */
    private function connection(): array
    {
        return ['origin' => 'https://example.test', 'email' => 'test@example.test', 'token' => 'expired',
            'refresh_token' => 'old-refresh', 'expires_at' => 1, 'generation' => 'login-1'];
    }
}
