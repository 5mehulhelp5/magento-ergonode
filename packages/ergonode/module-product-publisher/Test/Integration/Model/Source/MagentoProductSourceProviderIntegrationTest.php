<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Integration\Model\Source;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Model\Source\ProductSourceData;
use Ergonode\ProductPublisher\Model\Source\ProductSourceStateBuilder;
use Ergonode\Publisher\Api\Rest\ClientInterface;
use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Model\Source\EmptyProductAttributePublicationSource;
use Ergonode\ProductPublisher\Model\Source\MagentoProductSourceProvider;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureBeforeTransaction;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class MagentoProductSourceProviderIntegrationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['sku' => 'minimal-source', 'name' => 'Mapped name'])]
    public function testOptionalSourceUpgradesTheAttributesLoadedFromMagento(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $base = $objectManager->create(MagentoProductSourceProvider::class, [
            'attributeSource' => new EmptyProductAttributePublicationSource(),
        ])->load(['minimal-source']);

        self::assertSame('minimal-source', $base->getProducts()['minimal-source']->getSku());
        self::assertNull($base->getProducts()['minimal-source']->getData('name'));
        self::assertSame([], $base->getStoreProducts());
        self::assertSame([], $base->getAttributeMappings());

        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([1 => 'en_GB']);
        $extended = $objectManager->create(MagentoProductSourceProvider::class, [
            'attributeSource' => $this->attributeSource(),
            'languageMappingProvider' => $languages,
        ])->load(['minimal-source']);

        self::assertSame('Mapped name', $extended->getProducts()['minimal-source']->getData('name'));
        self::assertSame('Mapped name', $extended->getStoreProducts()[1]['minimal-source']->getData('name'));
        self::assertNull($extended->getProducts()['minimal-source']->getData('price'));
        self::assertSame(
            ['title'],
            $extended->getAttributeCodesForSet((int)$extended->getProducts()['minimal-source']->getAttributeSetId())
        );
    }

    #[DataFixtureBeforeTransaction(StoreFixture::class, as: 'publication_store')]
    #[DataFixture(ProductFixture::class, ['sku' => 'inherited-source', 'name' => 'Inherited default name'])]
    #[DataFixture(ProductFixture::class, ['sku' => 'overridden-source', 'name' => 'Default name'])]
    #[DataFixture(
        ProductFixture::class,
        ['sku' => 'overridden-source', 'name' => 'English edition', '_update' => true],
        scope: 'publication_store'
    )]
    public function testStoreProductsUseNativeDefaultInheritanceAndExplicitOverrides(): void
    {
        $storeId = (int)DataFixtureStorageManager::getStorage()->get('publication_store')->getId();
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'en_GB', $storeId => 'en_GB']);
        $data = Bootstrap::getObjectManager()->create(MagentoProductSourceProvider::class, [
            'attributeSource' => $this->attributeSource(),
            'languageMappingProvider' => $languages,
        ])->load(['inherited-source', 'overridden-source']);

        self::assertSame('Default name', $data->getProducts()['overridden-source']->getData('name'));
        self::assertSame(
            'Inherited default name',
            $data->getStoreProducts()[$storeId]['inherited-source']->getData('name')
        );
        self::assertSame('English edition', $data->getStoreProducts()[$storeId]['overridden-source']->getData('name'));
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'without-rest'], 'publication_product')]
    public function testPreparationWithoutAttributeValuesNeedsNoRestSessionOrCompletenessWarnings(): void
    {
        $objects = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objects);
        $rest = $this->createMock(ClientInterface::class);
        $rest->expects(self::never())->method('request');
        $objects->addSharedInstance($rest, ClientInterface::class);
        try {
            $product = DataFixtureStorageManager::getStorage()->get('publication_product');
            $productId = (int)$product->getId();
            $identities = $this->createStub(ProductIdentityRegistryInterface::class);
            $identities->method('getIdentitiesByProductIds')->willReturn([]);
            $identities->method('getMappedSkuValuesByProductIds')->willReturn([$productId => 'GLOBAL-1']);
            $identities->method('findMappedSkuProductIds')->willReturn(['GLOBAL-1' => $productId]);
            $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
            $mode->method('getMode')->willReturn(ProductIdentityModeProviderInterface::MODE_MAPPED);
            $builder = $objects->create(ProductSourceStateBuilder::class, [
                'attributeSource' => new EmptyProductAttributePublicationSource(),
                'identityRegistry' => $identities,
                'identityModeProvider' => $mode,
                'decorators' => [],
            ]);
            $result = $builder->build(new ProductSourceData(
                ['without-rest' => $product],
                [],
                [],
                [(int)$product->getAttributeSetId() => 'default'],
                []
            ), ['without-rest']);

            self::assertCount(1, $result->getStates());
            self::assertSame('GLOBAL-1', $result->getStates()[0]->getErgonodeSku());
            self::assertTrue($result->isAuthoritative());
            self::assertSame([], $result->getProductWarnings());
            self::assertSame([], $result->getSkippedProductWarnings());
        } finally {
            $objects->removeSharedInstance(ClientInterface::class);
        }
    }

    private function attributeSource(): ProductAttributePublicationSourceInterface
    {
        $source = $this->createStub(ProductAttributePublicationSourceInterface::class);
        $source->method('getMappings')->willReturn([1 => [
            'mapping_id' => 1,
            'magento_attribute_code' => 'name',
            'ergonode_attribute_code' => 'title',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'option_ids' => [],
        ]]);

        return $source;
    }
}
