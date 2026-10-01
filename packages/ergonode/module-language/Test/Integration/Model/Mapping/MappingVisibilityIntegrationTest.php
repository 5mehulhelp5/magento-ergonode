<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Integration\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;
use Ergonode\Language\Exception\AdminLanguageMappingRequiredException;
use Ergonode\Language\Exception\NoActiveLanguageMappingException;
use Ergonode\Language\Model\Mapping\MappingCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class MappingVisibilityIntegrationTest extends TestCase
{
    #[DataProvider('visibilitySources')]
    public function testVisibilityWritesUpdateCachedRuntimeMapAndCanBeReversed(string $source): void
    {
        $manager = Bootstrap::getObjectManager();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName('ergonode_language_store_mapping'));
        $connection->delete($resource->getTableName('ergonode_mapping_visibility'), ['entity_type = ?' => 'language']);
        $connection->insertArray(
            $resource->getTableName('ergonode_language'),
            ['language_code'],
            [['visibility_admin'], ['visibility_store']]
        );
        $store = $manager->get(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertNotNull($store);
        $storeId = (int)$store->getId();
        self::assertGreaterThan(0, $storeId);
        $stateProvider = $manager->get(LanguageMappingStateProviderInterface::class);
        $saver = $manager->get(LanguageStoreMappingSaverInterface::class);
        $provider = $manager->get(LanguageStoreMappingProviderInterface::class);
        $saver->save([
            ['store_id' => 0, 'language_code' => 'visibility_admin'],
            ['store_id' => $storeId, 'language_code' => 'visibility_store'],
        ], [], $stateProvider->getState()->revision);
        $original = $stateProvider->getState();
        $expected = [0 => 'visibility_admin', $storeId => 'visibility_store'];
        self::assertSame($expected, $provider->getLanguageStoreMap());

        // Disabling the admin language leaves a usable store map but blocks operations requiring scope zero.
        $saver->save($original->rows, [
            ['source' => 'ergo', 'code' => 'visibility_admin', 'active' => false],
        ], $original->revision);
        self::assertSame([$storeId => 'visibility_store'], $provider->getLanguageStoreMap());
        self::assertNull($provider->getAdminLanguageCode());
        try {
            $provider->requireAdminLanguageCode();
            self::fail('Inactive admin language remained available for publication.');
        } catch (AdminLanguageMappingRequiredException) {
            self::assertSame($original->rows, $stateProvider->getState()->rows);
        }

        $code = $source === 'ergo' ? 'visibility_store' : (string)$storeId;
        $state = $stateProvider->getState();
        $saver->save($state->rows, [['source' => $source, 'code' => $code, 'active' => false]], $state->revision);
        self::assertNotSame($state->revision, $stateProvider->getState()->revision);
        try {
            $provider->getLanguageStoreMap();
            self::fail('The final disabled mapping remained in the runtime cache.');
        } catch (NoActiveLanguageMappingException) {
            self::assertNull($provider->getAdminLanguageCode());
        }

        $state = $stateProvider->getState();
        $saver->save($state->rows, [
            ['source' => $source, 'code' => $code, 'active' => true],
            ['source' => 'ergo', 'code' => 'visibility_admin', 'active' => true],
        ], $state->revision);
        self::assertSame($expected, $provider->getLanguageStoreMap());
        self::assertSame('visibility_admin', $provider->requireAdminLanguageCode());
        self::assertSame($original->rows, $stateProvider->getState()->rows);
    }

    /** @return array<string, array{string}> */
    public static function visibilitySources(): array
    {
        return ['language visibility' => ['ergo'], 'store visibility' => ['magento']];
    }

    protected function tearDown(): void
    {
        Bootstrap::getObjectManager()->get(MappingCache::class)->invalidate();
        parent::tearDown();
    }
}
