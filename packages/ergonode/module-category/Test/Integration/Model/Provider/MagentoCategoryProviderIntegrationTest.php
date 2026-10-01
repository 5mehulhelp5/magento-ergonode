<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Integration\Model\Provider;

use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['parent_id' => 1], as: 'root')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$root.id$'], as: 'child')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$child.id$'], as: 'grandchild')]
#[DataFixture(CategoryFixture::class, ['parent_id' => 1], as: 'outside')]
class MagentoCategoryProviderIntegrationTest extends TestCase
{
    public function testIdsIncludeOnlyRootAndDescendantsAndIgnorePreviouslyLoadedModels(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $rootId = (int)$fixtures->get('root')->getId();
        $childId = (int)$fixtures->get('child')->getId();
        $grandchildId = (int)$fixtures->get('grandchild')->getId();
        $outsideId = (int)$fixtures->get('outside')->getId();
        $provider = $manager->create(MagentoCategoryProvider::class);
        self::assertEqualsCanonicalizing(
            [$rootId, $childId, $grandchildId],
            $provider->getIds($rootId)
        );
        self::assertEqualsCanonicalizing(array_keys($provider->getCategories($rootId)), $provider->getIds($rootId));

        // Keep the full-model cache warm while actual membership changes in the database.
        $manager->create(Category::class)->load($grandchildId)->move($outsideId, 0);
        self::assertEqualsCanonicalizing([$rootId, $childId], $provider->getIds($rootId));
        self::assertEqualsCanonicalizing([$outsideId, $grandchildId], $provider->getIds($outsideId));
        self::assertSame([], $provider->getIds(0));
        self::assertSame([], $provider->getIds(-1));
    }

    public function testIdsUseOneQueryWithoutEavAttributeReads(): void
    {
        $manager = Bootstrap::getObjectManager();
        $rootId = (int)DataFixtureStorageManager::getStorage()->get('root')->getId();
        $provider = $manager->create(MagentoCategoryProvider::class);
        $provider->getIds($rootId); // Initialize Magento metadata before counting actual reads.
        $profiler = $manager->get(ResourceConnection::class)->getConnection()->getProfiler();
        $enabled = $profiler->getEnabled();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            self::assertCount(3, $provider->getIds($rootId));
            $queries = $profiler->getQueryProfiles();
            self::assertIsArray($queries);
            self::assertCount(1, $queries);
            self::assertStringNotContainsString('catalog_category_entity_varchar', $queries[0]->getQuery());
        } finally {
            $profiler->clear();
            $profiler->setEnabled($enabled);
        }
    }
}
