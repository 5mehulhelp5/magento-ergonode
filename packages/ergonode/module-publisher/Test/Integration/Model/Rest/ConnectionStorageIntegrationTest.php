<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Integration\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Ergonode\Publisher\Model\ResourceModel\Rest\ConnectionStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ConnectionStorageIntegrationTest extends TestCase
{
    public function testEncryptsBothTokensReplacesPairAndSeparatesProfiles(): void
    {
        $manager = Bootstrap::getObjectManager();
        $storage = $manager->get(ConnectionStorageInterface::class);
        $data = ['origin' => 'https://example.test', 'email' => 'integration@example.test',
            'token' => 'access-fixture', 'refresh_token' => 'refresh-fixture',
            'expires_at' => time() + 3600, 'generation' => str_repeat('a', 32)];
        $storage->save('test', $data);
        $storage->save('production', $data);
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $row = $connection->fetchRow($connection->select()
            ->from($resource->getTableName(ConnectionStorage::TABLE))->where('profile = ?', 'test'));
        self::assertNotSame($data['token'], $row['token']);
        self::assertNotSame($data['refresh_token'], $row['refresh_token']);
        self::assertSame($data['token'], $storage->get('test')['token']);
        self::assertSame($data['refresh_token'], $storage->get('test')['refresh_token']);
        $data['token'] = 'renewed-access-fixture';
        $data['refresh_token'] = 'renewed-refresh-fixture';
        $storage->save('test', $data);
        self::assertSame($data['token'], $storage->get('test')['token']);
        self::assertSame($data['refresh_token'], $storage->get('test')['refresh_token']);
        self::assertSame('refresh-fixture', $storage->get('production')['refresh_token']);
        $storage->delete('test');
        self::assertNull($storage->get('test'));
        self::assertNotNull($storage->get('production'));
    }
}
