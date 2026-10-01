<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Storage;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Model\LanguageProvider;
use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Mapping\MappingLock;
use Ergonode\Language\Model\Storage\LanguageCodeStorage;
use Fiber;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\Backend\Database;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppIsolation(true), DbIsolation(false)]
class LanguageRefreshConcurrencyIntegrationTest extends TestCase
{
    public function testConcurrentRefreshCannotOvertakeDelayedResponse(): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->create(ResourceConnection::class);
        $otherResource = $manager->create(ResourceConnection::class);
        self::assertNotSame(
            $resource->getConnection()->fetchOne('SELECT CONNECTION_ID()'),
            $otherResource->getConnection()->fetchOne('SELECT CONNECTION_ID()')
        );
        self::assertTrue($manager->get(DeploymentConfig::class)->isDbAvailable(), 'DB lock backend must be enabled.');
        // Integration DI replaces Database with DummyLocker; construct the real backend explicitly.
        $deploymentConfig = $manager->get(DeploymentConfig::class);
        $locks = new Database($resource, $deploymentConfig);
        $otherLocks = new Database($otherResource, $deploymentConfig);
        $storage = new LanguageCodeStorage($resource);
        $otherStorage = new LanguageCodeStorage($otherResource);
        $original = $storage->getCodes();
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $client->method('query')->willReturnCallback(static function (): array {
            Fiber::suspend();
            return self::response(['en_GB']);
        });
        $newClient = $this->createMock(GraphQlQueryClientInterface::class);
        $newClient->expects(self::once())->method('query')->willReturn(self::response(['en_GB', 'pl_PL']));
        $cache = $manager->get(MappingCache::class);
        $first = new LanguageProvider($client, $storage, new MappingLock($locks), $cache, $locks);
        $second = new LanguageProvider($newClient, $otherStorage, new MappingLock($otherLocks), $cache, $otherLocks);
        $pending = new Fiber($first->refreshErgonodeLanguageCodes(...));

        try {
            $storage->replace(['en_GB', 'pl_PL']);
            $pending->start();
            self::assertTrue($pending->isSuspended());
            self::assertTrue($locks->isLocked('ergonode_language_refresh'), 'Refresh must hold its DB lock.');
            self::assertTrue(
                $otherLocks->isLocked('ergonode_language_refresh'),
                'Both sessions must see the same lock.'
            );
            // The other DB session can enter the mapping critical section while HTTP is pending.
            self::assertSame(['en_GB', 'pl_PL'], (new MappingLock($otherLocks))->run($otherStorage->getCodes(...)));
            try {
                $second->refreshErgonodeLanguageCodes();
                self::fail('A second refresh overtook the pending remote response.');
            } catch (LocalizedException $exception) {
                self::assertSame('Languages are already being refreshed. Please try again.', $exception->getMessage());
            }
            self::assertSame(['en_GB', 'pl_PL'], $otherStorage->getCodes());
            $pending->resume();
            self::assertSame(['en_GB'], $pending->getReturn());
            self::assertSame(['en_GB'], $otherStorage->getCodes());
            self::assertSame(['en_GB', 'pl_PL'], $second->refreshErgonodeLanguageCodes());
            self::assertSame(['en_GB', 'pl_PL'], $storage->getCodes());
        } finally {
            if ($pending->isSuspended()) {
                try {
                    $pending->throw(new RuntimeException('Abort pending test refresh.'));
                } catch (RuntimeException) {
                    // The provider's finally releases its acquired lock before fixture restoration.
                }
            }
            $storage->replace($original);
            $cache->invalidate();
            $resource->closeConnection();
            $otherResource->closeConnection();
        }
    }

    public function testRemoteFailureAllowsAnotherDatabaseSessionToRetry(): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->create(ResourceConnection::class);
        $otherResource = $manager->create(ResourceConnection::class);
        // Integration DI replaces Database with DummyLocker; construct the real backend explicitly.
        $deploymentConfig = $manager->get(DeploymentConfig::class);
        $locks = new Database($resource, $deploymentConfig);
        $otherLocks = new Database($otherResource, $deploymentConfig);
        $storage = new LanguageCodeStorage($resource);
        $original = $storage->getCodes();
        $cache = $manager->get(MappingCache::class);
        $client = $this->createStub(GraphQlQueryClientInterface::class);
        $failure = new RuntimeException('Injected remote failure.');
        $client->method('query')->willThrowException($failure);
        $first = new LanguageProvider($client, $storage, new MappingLock($locks), $cache, $locks);
        $retryClient = $this->createStub(GraphQlQueryClientInterface::class);
        $retryClient->method('query')->willReturn(self::response(['pl_PL']));
        $second = new LanguageProvider(
            $retryClient,
            new LanguageCodeStorage($otherResource),
            new MappingLock($otherLocks),
            $cache,
            $otherLocks
        );
        try {
            try {
                $first->refreshErgonodeLanguageCodes();
                self::fail('Remote failure was ignored.');
            } catch (RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }
            self::assertSame($original, $storage->getCodes());
            self::assertSame(['pl_PL'], $second->refreshErgonodeLanguageCodes());
            self::assertSame(['pl_PL'], $storage->getCodes());
        } finally {
            $storage->replace($original);
            $cache->invalidate();
            $resource->closeConnection();
            $otherResource->closeConnection();
        }
    }

    /** @param string[] $codes */
    private static function response(array $codes): array
    {
        return ['languageList' => [
            'edges' => array_map(static fn (string $code): array => ['node' => $code], $codes),
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
        ]];
    }
}
