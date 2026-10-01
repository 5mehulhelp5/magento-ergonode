<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Test\Unit\Model\Publisher;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributePublisher\Model\Publisher\CategoryReferenceSourceValidator;
use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourcePoolInterface;
use Ergonode\ProductPublisher\Api\ProductTypeMapperInterface;
use Ergonode\ProductPublisher\Model\Source\ProductSourceData;
use Ergonode\ProductPublisher\Model\Source\ProductSourceStateBuilder;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValuesResultDto;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class RequiredCategoryPublicationTest extends TestCase
{
    public function testMissingCategoryValueDoesNotRequireTemplateCompleteness(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $product->method('getCategoryIds')->willReturn([]);
        $mapping = ['magento_attribute_code' => 'default_category', 'ergonode_attribute_code' => 'category'];
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturn(true);
        $validator = new CategoryReferenceSourceValidator($config);
        $attributes = $this->createMock(ProductAttributePublicationSourceInterface::class);
        $attributes->expects(self::once())->method('getValues')->with($product, [], [$mapping])->willReturnCallback(
            static function (
                Product $product,
                array $stores,
                array $mappings
            ) use ($validator): ProductAttributeValuesResultDto {
                $validator->validate($product, $mappings[0], [], []);

                return new ProductAttributeValuesResultDto([]);
            }
        );
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $state = $this->createStub(ProductStateInterface::class);
        $factory->expects(self::once())->method('createProduct')->willReturn($state);
        $relations = $this->createMock(ProductRelationSourcePoolInterface::class);
        $relations->expects(self::once())->method('extract')->willReturn(
            $this->createStub(ProductRelationSourceResultInterface::class)
        );
        $types = $this->createStub(ProductTypeMapperInterface::class);
        $types->method('map')->willReturn('simple');
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getIdentitiesByProductIds')->willReturn([]);
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn('shared');
        $builder = new ProductSourceStateBuilder(
            $factory,
            $types,
            $attributes,
            $relations,
            $identities,
            $mode
        );

        $result = $builder->build(new ProductSourceData(
            ['SKU-1' => $product],
            [],
            [$mapping],
            [1 => 'template'],
            [1 => ['category']]
        ), ['SKU-1']);

        self::assertSame([$state], $result->getStates());
        self::assertTrue($result->isAuthoritative());
        self::assertSame([], $result->getSkippedProductMessages());
        self::assertSame([], $result->getSkippedProductWarnings());
        self::assertSame([], $result->getProductWarnings());
    }
}
