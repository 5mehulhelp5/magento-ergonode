<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Test\Unit\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Ergonode\PublisherAdminUi\Model\Rest\ConnectionStorage;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;

class ConnectionStorageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sessionData = [];

    public function testUncheckedLoginAndRefreshNeverWritePersistentCredentials(): void
    {
        $persistent = $this->createMock(ConnectionStorageInterface::class);
        $persistent->expects(self::never())->method('save');
        $storage = $this->storage($persistent);
        $storage->selectPersistence(false);
        $storage->save('test', $this->credentials());
        self::assertSame('encrypted:access', $this->sessionData['test']['token']);
        self::assertSame('encrypted:refresh', $this->sessionData['test']['refresh_token']);
        self::assertSame($this->credentials(), $storage->get('test'));

        // A later HTTP request rotates both tokens in the same session store.
        $storage = $this->storage($persistent);
        $renewed = array_replace($storage->get('test'), ['token' => 'new-access', 'refresh_token' => 'new-refresh']);
        $storage->save('test', $renewed);
        self::assertSame($renewed, $storage->get('test'));
    }

    public function testRememberedLoginUsesPersistentStorageAndClearsSessionOverride(): void
    {
        $persistent = $this->createMock(ConnectionStorageInterface::class);
        $persistent->expects(self::once())->method('save')->with('test', $this->credentials());
        $persistent->method('get')->with('test')->willReturn($this->credentials());
        $storage = $this->storage($persistent);
        $storage->selectPersistence(false);
        $storage->save('test', $this->credentials());
        $storage->selectPersistence(true);
        $storage->save('test', $this->credentials());
        self::assertArrayNotHasKey('test', $this->sessionData);
        self::assertSame($this->credentials(), $this->storage($persistent)->get('test'));
    }

    public function testRejectedSessionDoesNotDeleteOrFallBackToSharedAccount(): void
    {
        $persistent = $this->createMock(ConnectionStorageInterface::class);
        $persistent->expects(self::never())->method('get');
        $persistent->expects(self::never())->method('delete');
        $storage = $this->storage($persistent);
        $storage->selectPersistence(false);
        $storage->save('test', $this->credentials());
        $storage->delete('test');
        self::assertNull($this->storage($persistent)->get('test'));
    }

    public function testSessionCredentialsAreSeparatedByEnvironmentAndEndWithAdminSession(): void
    {
        $persistent = $this->createStub(ConnectionStorageInterface::class);
        $storage = $this->storage($persistent);
        $storage->selectPersistence(false);
        $storage->save('test', $this->credentials());
        self::assertNull($storage->get('production'));
        $this->sessionData = [];
        self::assertNull($this->storage($persistent)->get('test'));
    }

    private function storage(ConnectionStorageInterface $persistent): ConnectionStorage
    {
        $session = $this->createStub(Session::class);
        $session->method('getData')->willReturnCallback(fn (): array => $this->sessionData);
        $session->method('__call')->willReturnCallback(function (string $method, array $args) use ($session): Session {
            self::assertSame('setData', $method);
            self::assertSame('ergonode_rest_connections', $args[0]);
            $this->sessionData = $args[1];
            return $session;
        });
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $value): string => 'encrypted:' . $value);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $value): string => substr($value, 10));
        return new ConnectionStorage($persistent, $session, $encryptor);
    }

    /** @return array<string, mixed> */
    private function credentials(): array
    {
        return ['origin' => 'https://example.test', 'email' => 'account@example.test',
            'token' => 'access', 'refresh_token' => 'refresh', 'expires_at' => 2000000000,
            'generation' => 'fixture'];
    }
}
