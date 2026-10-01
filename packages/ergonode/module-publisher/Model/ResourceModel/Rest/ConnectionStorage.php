<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\ResourceModel\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;

class ConnectionStorage implements ConnectionStorageInterface
{
    public const string TABLE = 'ergonode_publisher_rest_connection';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function get(string $profile): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow($connection->select()
            ->from($this->resource->getTableName(self::TABLE))->where('profile = ?', $profile));
        if (!$row) {
            return null;
        }
        $row['token'] = $this->encryptor->decrypt($row['token']);
        $row['refresh_token'] = $this->encryptor->decrypt($row['refresh_token']);
        return $row;
    }

    public function save(string $profile, array $connection): void
    {
        $row = array_intersect_key($connection, array_flip([
            'origin', 'email', 'token', 'refresh_token', 'expires_at', 'generation',
        ]));
        $row['profile'] = $profile;
        $row['token'] = $this->encryptor->encrypt((string)$row['token']);
        $row['refresh_token'] = $this->encryptor->encrypt((string)$row['refresh_token']);
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            $row,
            ['origin', 'email', 'token', 'refresh_token', 'expires_at', 'generation']
        );
    }

    public function delete(string $profile): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['profile = ?' => $profile]
        );
    }
}
