<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Test\Integration\Model\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionStorageInterface;
use Ergonode\PublisherAdminUi\Model\Rest\ConnectionStorage;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml')]
#[DbIsolation(true)]
class ConnectionStorageIntegrationTest extends TestCase
{
    public function testAdminPreferenceStoresOnlyRememberedTokensInDatabase(): void
    {
        $manager = Bootstrap::getObjectManager();
        $storage = $manager->get(ConnectionStorageInterface::class);
        self::assertInstanceOf(ConnectionStorage::class, $storage);
        self::assertSame($storage, $manager->get(ConnectionStorage::class));
        $resource = $manager->get(ResourceConnection::class);
        $db = $resource->getConnection();
        $table = $resource->getTableName('ergonode_publisher_rest_connection');
        $db->delete($table, ['profile = ?' => 'test']);
        $data = ['origin' => 'https://example.test', 'email' => 'fixture@example.test',
            'token' => 'access-fixture', 'refresh_token' => 'refresh-fixture',
            'expires_at' => time() + 3600, 'generation' => str_repeat('a', 32)];
        try {
            $storage->selectPersistence(false);
            $storage->save('test', $data);
            $query = $db->select()->from($table)->where('profile = ?', 'test');
            self::assertFalse($db->fetchRow($query));
            self::assertSame($data, $storage->get('test'));
            $session = $manager->get(Session::class)->getData('ergonode_rest_connections');
            self::assertNotSame($data['token'], $session['test']['token']);
            self::assertNotSame($data['refresh_token'], $session['test']['refresh_token']);
            $storage->selectPersistence(true);
            $storage->save('test', $data);
            $row = $db->fetchRow($query);
            self::assertNotSame($data['token'], $row['token']);
            self::assertNotSame($data['refresh_token'], $row['refresh_token']);
            $manager->get(Session::class)->unsetData('ergonode_rest_connections');
            self::assertSame($data['token'], $storage->get('test')['token']);
        } finally {
            $manager->get(Session::class)->unsetData('ergonode_rest_connections');
        }
    }
}
