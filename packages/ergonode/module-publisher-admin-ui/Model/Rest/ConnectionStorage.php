<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Encryption\EncryptorInterface;

class ConnectionStorage implements ConnectionStorageInterface
{
    private const string SESSION_KEY = 'ergonode_rest_connections';

    private ?bool $persistent = null;

    public function __construct(
        private readonly ConnectionStorageInterface $persistentStorage,
        private readonly Session $session,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function selectPersistence(bool $persistent): void
    {
        $this->persistent = $persistent;
    }

    public function get(string $profile): ?array
    {
        $connections = $this->connections();
        if (!array_key_exists($profile, $connections)) {
            return $this->persistentStorage->get($profile);
        }
        $connection = $connections[$profile];
        if ($connection !== null) {
            $connection['token'] = $this->encryptor->decrypt($connection['token']);
            $connection['refresh_token'] = $this->encryptor->decrypt($connection['refresh_token']);
        }
        return $connection;
    }

    public function save(string $profile, array $connection): void
    {
        $connections = $this->connections();
        $persistent = $this->persistent ?? !array_key_exists($profile, $connections);
        if ($persistent) {
            $this->persistentStorage->save($profile, $connection);
            unset($connections[$profile]);
        } else {
            $connection['token'] = $this->encryptor->encrypt((string)$connection['token']);
            $connection['refresh_token'] = $this->encryptor->encrypt((string)$connection['refresh_token']);
            $connections[$profile] = $connection;
        }
        $this->session->setData(self::SESSION_KEY, $connections);
        $this->persistent = null;
    }

    public function delete(string $profile): void
    {
        $connections = $this->connections();
        if (array_key_exists($profile, $connections)) {
            // Keep an empty session override after rejection; never switch accounts implicitly.
            $connections[$profile] = null;
            $this->session->setData(self::SESSION_KEY, $connections);
            return;
        }
        $this->persistentStorage->delete($profile);
    }

    /** @return array<string, array<string, mixed>|null> */
    private function connections(): array
    {
        $connections = $this->session->getData(self::SESSION_KEY);
        return is_array($connections) ? $connections : [];
    }
}
