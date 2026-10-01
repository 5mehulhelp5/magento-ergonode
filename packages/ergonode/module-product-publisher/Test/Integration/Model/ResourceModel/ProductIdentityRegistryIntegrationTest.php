<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Integration\Model\ResourceModel;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class ProductIdentityRegistryIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'magento-mapped-product'], as: 'mapped_product')]
    public function testMappedIdentityKeepsNativeSkuWhenRecordingPublication(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('mapped_product');
        self::assertInstanceOf(Product::class, $product);
        $registry = Bootstrap::getObjectManager()->get(ProductIdentityRegistryInterface::class);

        $registry->bindMappedBatch([(int)$product->getId() => 'NAV-1']);
        $registry->recordPublished([(int)$product->getId() => 'magento-mapped-product']);

        $identity = $registry->getIdentitiesByProductIds([(int)$product->getId()])[(int)$product->getId()];
        self::assertSame('NAV-1', $identity->getErgonodeSku());
        self::assertSame(ProductIdentityInterface::MODE_MAPPED, $identity->getIdentityMode());
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'assigned-batch-one'], as: 'assigned_batch_one')]
    #[DataFixture(ProductFixture::class, ['sku' => 'assigned-batch-two'], as: 'assigned_batch_two')]
    public function testAssignedIdentitiesArePersistedAsOneBatch(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $first = $storage->get('assigned_batch_one');
        $second = $storage->get('assigned_batch_two');
        self::assertInstanceOf(Product::class, $first);
        self::assertInstanceOf(Product::class, $second);
        $registry = Bootstrap::getObjectManager()->get(ProductIdentityRegistryInterface::class);

        $registry->bindAssignedBatch([
            (int)$first->getId() => '1000000001',
            (int)$second->getId() => '1000000002',
        ]);

        $identities = $registry->getIdentitiesByProductIds([(int)$first->getId(), (int)$second->getId()]);
        self::assertSame('1000000001', $identities[(int)$first->getId()]->getErgonodeSku());
        self::assertSame('1000000002', $identities[(int)$second->getId()]->getErgonodeSku());
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'unbound-publication'], as: 'unbound')]
    public function testPublicationCannotCreateHistoricalSharedBinding(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('unbound');
        self::assertInstanceOf(Product::class, $product);
        $registry = Bootstrap::getObjectManager()->get(ProductIdentityRegistryInterface::class);

        $this->expectException(LocalizedException::class);
        $registry->recordPublished([(int)$product->getId() => 'unbound-publication']);
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'ergonode-mapped-product'], as: 'product')]
    public function testPersistedSkuIsIdempotentAndImmutable(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $mapping = [(int)$product->getId() => 'ergonode-mapped-product'];
        $registry = Bootstrap::getObjectManager()->get(ProductIdentityRegistryInterface::class);

        $registry->bindAssignedBatch($mapping);
        $registry->recordPublished($mapping);
        $registry->recordPublished($mapping);
        $registry->assertStable($mapping);
        self::assertSame([], $registry->getStabilityFailures($mapping));
        self::assertSame([], $registry->getStabilityFailures([(int)$product->getId() => 'changed-sku']));
    }
}
