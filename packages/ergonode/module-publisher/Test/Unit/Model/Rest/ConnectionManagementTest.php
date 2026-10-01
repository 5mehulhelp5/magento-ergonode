<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Ergonode\Publisher\Model\Rest\AccessTokenProvider;
use Ergonode\Publisher\Model\Rest\ConnectionContext;
use Ergonode\Publisher\Model\Rest\ConnectionLock;
use Ergonode\Publisher\Model\Rest\ConnectionManagement;
use Ergonode\Publisher\Model\Rest\TokenPair;
use Ergonode\Publisher\Model\Rest\Transport;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ConnectionManagementTest extends TestCase
{
    public function testStatusRenewsTokensBeforeDeclaringConnectionAuthenticated(): void
    {
        $tokens = $this->createMock(AccessTokenProvider::class);
        $tokens->expects(self::once())->method('get')->with('test', 'https://example.test')
            ->willReturn(['email' => 'account@example.test']);
        self::assertSame(
            ['authenticated' => true, 'email' => 'account@example.test'],
            $this->connection($tokens)->status()
        );
    }

    public function testRejectedRefreshRequiresLoginWithoutReturningCredentials(): void
    {
        $tokens = $this->createStub(AccessTokenProvider::class);
        $tokens->method('get')->willThrowException(new AuthenticationException(__('Expired.')));
        self::assertSame(['authenticated' => false, 'email' => ''], $this->connection($tokens)->status());
    }

    public function testTransientRefreshFailureIsNotReportedAsMissingCredentials(): void
    {
        $tokens = $this->createStub(AccessTokenProvider::class);
        $tokens->method('get')->willThrowException(new LocalizedException(__('Unavailable.')));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unavailable.');
        $this->connection($tokens)->status();
    }

    public function testLoginAndDisconnectPersistOnlyTheActiveConnectionWithoutMetadataCache(): void
    {
        $context = $this->createStub(ConnectionContext::class);
        $context->method('profile')->willReturn('test');
        $context->method('origin')->willReturn('https://example.test');
        $expiresAt = time() + 3600;
        $token = 'header.' . base64_encode((string)json_encode(['exp' => $expiresAt])) . '.signature';
        $transport = $this->createMock(Transport::class);
        $transport->expects(self::once())->method('request')->with(
            'test',
            'https://example.test',
            'POST',
            '/api/v1/login',
            ['username' => 'account@example.test', 'password' => 'test-password']
        )->willReturn(['token' => $token, 'refresh_token' => 'test-refresh']);
        $storage = $this->createMock(ConnectionStorageInterface::class);
        $storage->expects(self::once())->method('save')->with('test', self::callback(
            static fn (array $connection): bool => $connection['token'] === $token
                && $connection['refresh_token'] === 'test-refresh'
                && $connection['expires_at'] === $expiresAt
                && $connection['origin'] === 'https://example.test'
                && $connection['email'] === 'account@example.test'
                && strlen($connection['generation']) === 32
                && !isset($connection['password'])
        ));
        $storage->expects(self::once())->method('delete')->with('test');
        $lock = $this->createMock(ConnectionLock::class);
        $lock->expects(self::exactly(2))->method('execute')->with('test', self::isCallable())
            ->willReturnCallback(static fn (string $profile, callable $operation): mixed => $operation());
        $service = new ConnectionManagement(
            $context,
            $storage,
            $lock,
            $transport,
            new TokenPair(),
            $this->createStub(AccessTokenProvider::class)
        );

        $service->login(' account@example.test ', 'test-password');
        $service->disconnect();
    }

    private function connection(AccessTokenProvider $tokens): ConnectionManagement
    {
        $context = $this->createStub(ConnectionContext::class);
        $context->method('profile')->willReturn('test');
        $context->method('origin')->willReturn('https://example.test');
        $storage = $this->createStub(ConnectionStorageInterface::class);
        $storage->method('get')->willReturn(['origin' => 'https://example.test',
            'token' => 'expired', 'refresh_token' => 'refresh', 'email' => 'account@example.test']);
        return new ConnectionManagement(
            $context,
            $storage,
            $this->createStub(ConnectionLock::class),
            $this->createStub(Transport::class),
            new TokenPair(),
            $tokens
        );
    }
}
