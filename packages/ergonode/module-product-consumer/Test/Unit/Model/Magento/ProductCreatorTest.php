<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\Magento\ProductCreator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductCreatorTest extends TestCase
{
    public function testAppliesConfiguredManualValuesOnlyDuringCreation(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $productFactory = $this->createStub(ProductFactory::class);
        $productFactory->method('create')->willReturn($product);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('save')->with($product)->willReturn($product);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([]);
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('getManualCreationValues')->willReturn([
            'price' => '12.50',
            'status' => 1,
            'visibility' => 3,
        ]);

        $creator = new ProductCreator(
            $productFactory,
            $productRepository,
            $this->createStub(StoreManagerInterface::class),
            $languageMappingProvider,
            $attributePolicy
        );

        self::assertSame($product, $creator->create(
            $this->remoteProduct(),
            'simple',
            17,
            ['default_category' => [0 => 42, 2 => 43]]
        ));
        self::assertSame('SKU-1', $product->getData(ProductInterface::SKU));
        self::assertSame('simple', $product->getData('type_id'));
        self::assertSame(17, $product->getData(ProductInterface::ATTRIBUTE_SET_ID));
        self::assertSame('SKU-1', $product->getData(ProductInterface::NAME));
        self::assertSame(1, $product->getData(ProductInterface::STATUS));
        self::assertSame(3, $product->getData(ProductInterface::VISIBILITY));
        self::assertSame('12.50', $product->getData(ProductInterface::PRICE));
        self::assertSame(0, $product->getData('tax_class_id'));
        self::assertSame([], $product->getData('website_ids'));
        self::assertSame(42, $product->getData('default_category'));
    }

    private function remoteProduct(): RemoteProduct
    {
        return new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
    }
}
