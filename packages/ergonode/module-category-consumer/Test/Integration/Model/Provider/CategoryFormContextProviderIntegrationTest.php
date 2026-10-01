<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Provider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Category\Api\CategoryCreationContextProviderInterface;
use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(CategoryFixture::class, ['name' => 'Collections'], as: 'parent_category'),
    DataFixture(
        CategoryFixture::class,
        [
            'name' => 'New Luma Yoga Collection',
            'url_key' => 'yoga-new',
            'parent_id' => '$parent_category.id$',
        ],
        as: 'category'
    )
]
class CategoryFormContextProviderIntegrationTest extends TestCase
{
    public function testExposesCodeOnlyAfterRemoteTreeSnapshotContainsMappedCategory(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $categoryId = (int)$this->fixture('category')->getId();
        $rootCategoryId = (int)$objectManager->get(StoreManagerInterface::class)
            ->getGroup((int)$objectManager->get(StoreManagerInterface::class)->getDefaultStoreView()->getStoreGroupId())
            ->getRootCategoryId();
        $categoryTreeId = $objectManager->get(CategoryTreeRepository::class)->save([
            'is_active' => true,
            'tree_code' => 'category-form-tree',
            'root_category_id' => $rootCategoryId,
            'remove_missing' => false,
        ]);
        $objectManager->get(CategoryMappingWriter::class)->saveLayout(
            $categoryTreeId,
            'mapped_category',
            null,
            1,
            $categoryId
        );
        $provider = $objectManager->get(CategoryFormContextProviderInterface::class);

        self::assertSame([
            'category_tree_id' => $categoryTreeId,
            'root_category_id' => $rootCategoryId,
            'ergonode_category_code' => null,
        ], $provider->getForMagentoCategory($categoryId));

        $objectManager->get(CategorySnapshotWriter::class)->saveCategories($categoryTreeId, [[
            'code' => 'mapped_category',
            'parent_code' => null,
            'labels' => ['en_US' => 'Mapped category'],
            'sort_order' => 1,
            'raw' => ['code' => 'mapped_category'],
            'hash' => hash('sha256', 'mapped_category'),
        ]]);

        $mappedContext = $provider->getForMagentoCategory($categoryId);
        self::assertNotNull($mappedContext);
        self::assertSame('mapped_category', $mappedContext['ergonode_category_code']);

        $creationContext = $objectManager->get(CategoryCreationContextProviderInterface::class)
            ->getForMagentoCategory($categoryId);
        self::assertNotNull($creationContext);
        self::assertSame(
            ['Collections', 'New Luma Yoga Collection'],
            $creationContext['category']['path_labels']
        );
    }

    private function fixture(string $alias): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return $fixture;
    }
}
