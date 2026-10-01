<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
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
    DataFixture(CategoryFixture::class, ['name' => 'Original category'], as: 'category')
]
class CategoryAttributeWriterIntegrationTest extends TestCase
{
    public function testPersistsMappedDefaultAndStoreScopedNames(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $categoryRepository = $objectManager->get(CategoryRepositoryInterface::class);
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        $storeId = (int)$storeManager->getDefaultStoreView()->getId();
        $categoryId = (int)$this->fixture('category')->getId();

        $valueMapper = $this->createStub(CategoryAttributeValueMapper::class);
        $valueMapper->method('mapForSynchronization')->willReturn([
            'values' => ['name' => [0 => 'Kategoria', $storeId => 'Category']],
            'clear' => [],
        ]);

        (new CategoryAttributeWriter(
            $objectManager->get(ResourceConnection::class),
            $objectManager->get(CategoryResource::class),
            $objectManager->get(EavConfig::class),
            $objectManager->get(CategoryRepository::class),
            $valueMapper
        ))->writeMappedValues($categoryId, []);

        self::assertSame('Kategoria', $categoryRepository->get($categoryId, 0)->getName());
        self::assertSame('Category', $categoryRepository->get($categoryId, $storeId)->getName());
    }

    private function fixture(string $alias): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return $fixture;
    }
}
