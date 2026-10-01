<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Storage;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Model\LanguageProvider;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppIsolation(true), DbIsolation(false)]
class LanguageSnapshotRollbackIntegrationTest extends TestCase
{
    public function testFailedReplacementRestoresSnapshotAndDoesNotInvalidateCache(): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $storage = $manager->get(LanguageCodeStorage::class);
        $original = $storage->getCodes();
        $failure = new RuntimeException('Injected snapshot insert failure.');

        try {
            $storage->replace(['rollback_snapshot_old']);
            $adapter = $this->createMock(AdapterInterface::class);
            foreach (['beginTransaction', 'delete', 'rollBack'] as $method) {
                $adapter->method($method)->willReturnCallback($connection->$method(...));
            }
            $adapter->expects(self::never())->method('commit');
            $adapter->expects(self::once())->method('insertArray')->willReturnCallback(
                static function () use ($storage, $failure): never {
                    self::assertSame([], $storage->getCodes(), 'Failure must happen after deleting the old snapshot.');
                    throw $failure;
                }
            );
            $failingResource = $this->createStub(ResourceConnection::class);
            $failingResource->method('getConnection')->willReturn($adapter);
            $failingResource->method('getTableName')->willReturnCallback($resource->getTableName(...));
            $client = $this->createStub(GraphQlQueryClientInterface::class);
            $client->method('query')->willReturn(['languageList' => [
                'edges' => [['node' => 'rollback_snapshot_new']],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            ]]);
            $cache = $this->createMock(MappingCache::class);
            $cache->expects(self::never())->method('invalidate');
            $provider = new LanguageProvider(
                $client,
                new LanguageCodeStorage($failingResource),
                $manager->get(MappingLock::class),
                $cache,
                $manager->get(LockManagerInterface::class)
            );

            try {
                $provider->refreshErgonodeLanguageCodes();
                self::fail('The injected snapshot failure was ignored.');
            } catch (RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }
            self::assertSame(['rollback_snapshot_old'], $storage->getCodes());
        } finally {
            $storage->replace($original);
            $manager->get(MappingCache::class)->invalidate();
        }
    }
}
