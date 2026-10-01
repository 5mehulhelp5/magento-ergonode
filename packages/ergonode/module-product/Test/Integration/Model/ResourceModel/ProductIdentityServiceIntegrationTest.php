<?php

declare(strict_types=1);

namespace Ergonode\Product\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ProductIdentityServiceIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'new-shared-forbidden'], as: 'new_shared')]
    public function testNewSharedIdentityIsRejected(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('new_shared');
        self::assertInstanceOf(Product::class, $product);
        $service = Bootstrap::getObjectManager()->get(ProductIdentityServiceInterface::class);

        $this->expectException(LocalizedException::class);
        $service->bind((int)$product->getId(), 'new-shared-forbidden', ProductIdentityInterface::MODE_SHARED);
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'historical-shared'], as: 'historical_shared')]
    public function testHistoricalSharedIdentityRemainsUsable(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('historical_shared');
        self::assertInstanceOf(Product::class, $product);
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('ergonode_product_mapping'), [
            'product_id' => (int)$product->getId(),
            'ergonode_sku' => 'historical-shared',
            'identity_mode' => ProductIdentityInterface::MODE_SHARED,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $service = $objectManager->get(ProductIdentityServiceInterface::class);

        $service->bind((int)$product->getId(), 'historical-shared', ProductIdentityInterface::MODE_SHARED);
        $service->recordImported((int)$product->getId(), 'historical-shared', hash('sha256', 'historical'));

        self::assertSame(
            ProductIdentityInterface::MODE_SHARED,
            $service->getIdentitiesByProductIds([(int)$product->getId()])[(int)$product->getId()]->getIdentityMode()
        );
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'batch-product-one'], as: 'batch_product_one')]
    #[DataFixture(ProductFixture::class, ['sku' => 'batch-product-two'], as: 'batch_product_two')]
    public function testAssignedIdentitiesAreBoundAsOneBatch(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $first = $storage->get('batch_product_one');
        $second = $storage->get('batch_product_two');
        self::assertInstanceOf(Product::class, $first);
        self::assertInstanceOf(Product::class, $second);
        $service = Bootstrap::getObjectManager()->get(ProductIdentityServiceInterface::class);

        $service->bindMany([
            (int)$first->getId() => [
                'ergonode_sku' => '1000000001',
                'identity_mode' => ProductIdentityInterface::MODE_ASSIGNED,
            ],
            (int)$second->getId() => [
                'ergonode_sku' => '1000000002',
                'identity_mode' => ProductIdentityInterface::MODE_ASSIGNED,
            ],
        ]);

        self::assertSame([
            (int)$first->getId() => '1000000001',
            (int)$second->getId() => '1000000002',
        ], $service->getErgonodeSkusByProductIds([(int)$first->getId(), (int)$second->getId()]));
        $identities = $service->getIdentitiesByErgonodeSkus(['1000000001', '1000000002']);
        $skus = array_map(
            static fn (ProductIdentityInterface $identity): string => $identity->getErgonodeSku(),
            $identities
        );
        sort($skus);
        self::assertSame(['1000000001', '1000000002'], $skus);
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'magento-owned-sku'], as: 'assigned_product')]
    public function testAssignedIdentityExposesNativeSkuAsReadOnlyExtensionAttribute(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('assigned_product');
        self::assertInstanceOf(Product::class, $product);
        $productId = (int)$product->getId();
        $objectManager = Bootstrap::getObjectManager();
        $service = $objectManager->get(ProductIdentityServiceInterface::class);

        $service->bind($productId, 'ERG-ASSIGNED-1', ProductIdentityInterface::MODE_ASSIGNED);

        $identity = $service->getIdentitiesByProductIds([$productId])[$productId];
        self::assertSame('magento-owned-sku', $identity->getMagentoSku());
        self::assertSame('ERG-ASSIGNED-1', $identity->getErgonodeSku());
        self::assertSame(ProductIdentityInterface::MODE_ASSIGNED, $identity->getIdentityMode());
        $loaded = $objectManager->get(ProductRepositoryInterface::class)->getById($productId, true);
        self::assertSame('ERG-ASSIGNED-1', $loaded->getExtensionAttributes()?->getErgonodeSku());
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'ergonode-imported-product'], as: 'product')]
    public function testImportedIdentityAndHashAreIdempotent(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $productId = (int)$product->getId();
        $service = Bootstrap::getObjectManager()->get(ProductIdentityServiceInterface::class);
        $hash = hash('sha256', 'payload');

        $service->bind($productId, 'ergonode-imported-product', ProductIdentityInterface::MODE_ASSIGNED);
        $service->recordImported($productId, 'ergonode-imported-product', $hash);
        $service->recordImported($productId, 'ergonode-imported-product', $hash);

        self::assertSame(
            $productId,
            $service->getIdentitiesByErgonodeSkus(['ergonode-imported-product'])[0]->getProductId()
        );
        self::assertSame([$productId => 'ergonode-imported-product'], $service->getErgonodeSkusByProductIds([
            $productId,
        ]));
        self::assertSame($hash, $service->getImportHash($productId));

        $service->clearImportHash('ergonode-imported-product');
        self::assertNull($service->getImportHash($productId));
    }
}
