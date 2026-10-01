<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Integration\Model\Magento;

use Ergonode\ProductConsumer\Model\Port\SelectedProductWriterInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Store\Model\Store;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// Store fixtures create index tables; their DDL cannot run inside the test transaction.
#[AppIsolation(true), DbIsolation(false)]
class SelectedProductWriterIntegrationTest extends TestCase
{
    #[DataFixture(StoreFixture::class, as: 'store')]
    #[DataFixture(ProductFixture::class, ['sku' => 'selected-import-%uniqid%', 'price' => 17], as: 'product')]
    public function testStoreTranslationAndUseDefaultKeepUnmappedData(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $product = $fixtures->get('product');
        $store = $fixtures->get('store');
        self::assertInstanceOf(Product::class, $product);
        self::assertInstanceOf(Store::class, $store);
        $id = (int)$product->getId();
        $storeId = (int)$store->getId();
        $writer = $manager->get(SelectedProductWriterInterface::class);
        $repository = $manager->get(ProductRepositoryInterface::class);
        $writer->write($id, ['name' => [0 => 'Default name', $storeId => 'Translated name']], []);
        self::assertSame('Default name', $repository->getById($id, true, 0, true)->getName());
        self::assertSame('Translated name', $repository->getById($id, true, $storeId, true)->getName());
        $writer->write($id, ['name' => [0 => 'Changed default']], ['name' => [$storeId]]);
        $reloaded = $repository->getById($id, true, $storeId, true);
        self::assertSame('Changed default', $reloaded->getName());
        self::assertSame($product->getSku(), $reloaded->getSku());
        self::assertSame(17.0, (float)$reloaded->getPrice());
        self::assertSame($product->getTypeId(), $reloaded->getTypeId());
        self::assertSame($product->getAttributeSetId(), $reloaded->getAttributeSetId());
    }
}
