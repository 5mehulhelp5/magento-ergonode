<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\CategoryConsumer\Model\ResourceModel\CategoryNameWriter;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true), DataFixture(CategoryFixture::class, ['name' => 'Original'], as: 'category')]
class CategoryNameWriterIntegrationTest extends TestCase
{
    public function testUpdatesNamesIdempotentlyAndRestoresStoreInheritanceWithoutChangingUrlKey(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $fixture = DataFixtureStorageManager::getStorage()->get('category');
        self::assertNotNull($fixture);
        $categoryId = (int)$fixture->getId();
        $repository = $objectManager->get(CategoryRepositoryInterface::class);
        $storeId = (int)$objectManager->get(StoreManagerInterface::class)->getDefaultStoreView()->getId();
        $urlKey = $repository->get($categoryId, 0)->getUrlKey();
        $writer = $objectManager->get(CategoryNameWriter::class);

        self::assertSame(2, $writer->write($categoryId, 'name', [0 => 'Chairs', $storeId => 'Krzesła']));
        self::assertSame('Chairs', $repository->get($categoryId, 0)->getName());
        self::assertSame('Krzesła', $repository->get($categoryId, $storeId)->getName());
        self::assertSame(0, $writer->write($categoryId, 'name', [0 => 'Chairs', $storeId => 'Krzesła']));
        self::assertSame(1, $writer->write($categoryId, 'name', [$storeId => null]));
        self::assertSame('Chairs', $repository->get($categoryId, $storeId)->getName());
        self::assertSame($urlKey, $repository->get($categoryId, 0)->getUrlKey());
    }

    public function testRejectsTextareaTarget(): void
    {
        $fixture = DataFixtureStorageManager::getStorage()->get('category');
        self::assertNotNull($fixture);
        $this->expectException(LocalizedException::class);
        Bootstrap::getObjectManager()->get(CategoryNameWriter::class)
            ->write((int)$fixture->getId(), 'description', [0 => 'Chairs']);
    }
}
