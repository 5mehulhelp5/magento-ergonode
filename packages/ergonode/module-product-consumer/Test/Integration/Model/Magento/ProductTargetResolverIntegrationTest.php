<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Integration\Model\Magento;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\ProductTargetResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class ProductTargetResolverIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'same-as-ergonode'], as: 'product')]
    public function testMappedModeDoesNotFallbackToMagentoSku(): void
    {
        $identityService = $this->createStub(ProductIdentityServiceInterface::class);
        $identityService->method('getIdentitiesByErgonodeSkus')->willReturn([]);
        $attribute = $this->createMock(MagentoIdentityAttributeInterface::class);
        $attribute->expects(self::once())->method('findProductId')->with('same-as-ergonode')->willReturn(null);
        $resolver = new ProductTargetResolver(
            Bootstrap::getObjectManager()->get(ResourceConnection::class),
            $identityService,
            $attribute
        );

        self::assertNull($resolver->resolve('same-as-ergonode', null, ProductIdentityInterface::MODE_MAPPED));
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'magento-only-sku'], as: 'product')]
    public function testMappedModeBindsProductFoundByIdentityAttribute(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $identityService = $this->createMock(ProductIdentityServiceInterface::class);
        $identityService->method('getIdentitiesByErgonodeSkus')->willReturn([]);
        $identityService->expects(self::once())->method('bind')->with(
            (int)$product->getId(),
            'NAV-42',
            ProductIdentityInterface::MODE_MAPPED
        );
        $attribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $attribute->method('findProductId')->willReturn((int)$product->getId());
        $resolver = new ProductTargetResolver(
            Bootstrap::getObjectManager()->get(ResourceConnection::class),
            $identityService,
            $attribute
        );

        self::assertSame('magento-only-sku', $resolver->resolve(
            'NAV-42',
            null,
            ProductIdentityInterface::MODE_MAPPED
        )['sku']);
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'historical-magento-sku'], as: 'product')]
    public function testHistoricalBindingAndDifferentIdentityAttributeBlockImport(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $productId = (int)$product->getId();
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getErgonodeSku')->willReturn('OLD-NATIVE');
        $identity->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_ASSIGNED);
        $identities = $this->createStub(ProductIdentityServiceInterface::class);
        $identities->method('getIdentitiesByProductIds')->willReturn([$productId => $identity]);
        $attribute = $this->createStub(MagentoIdentityAttributeInterface::class);
        $attribute->method('getValuesByProductIds')->willReturn([$productId => 'NEW-NATIVE']);
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn(ProductIdentityInterface::MODE_MAPPED);
        $synchronizer = new MagentoSkuSynchronizer(
            Bootstrap::getObjectManager()->get(ResourceConnection::class),
            $this->createStub(ProductRepositoryInterface::class),
            $identities,
            $attribute,
            $mode
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Reconcile or migrate');
        $synchronizer->validate($productId, 'historical-magento-sku', 'OLD-NATIVE');
    }
}
