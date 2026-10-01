<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourcePoolInterface;
use Ergonode\ProductPublisher\Api\ProductTypeMapperInterface;
use Ergonode\ProductPublisher\Model\Source\EmptyProductAttributePublicationSource;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValuesResultDto;
use Ergonode\ProductPublisher\Model\Source\ProductSourceData;
use Ergonode\ProductPublisher\Model\Source\ProductSourceStateBuilder;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductSourceStateBuilderTest extends TestCase
{
    public function testProductStatePreservesOnlyAttributeSourceWarnings(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $attributes = $this->createStub(ProductAttributePublicationSourceInterface::class);
        $attributes->method('getValues')->willReturn(new ProductAttributeValuesResultDto(
            [],
            ['Existing attribute warning.']
        ));
        $state = $this->createStub(ProductStateInterface::class);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createProduct')->willReturn($state);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            $attributes,
            $this->relationSourcePool(),
            $this->identityRegistry(),
            $this->attributePolicy()
        );

        $result = $builder->build(new ProductSourceData(
            ['SKU-1' => $product],
            [],
            [],
            [1 => 'template'],
            [1 => []]
        ), ['SKU-1']);

        self::assertSame([$state], $result->getStates());
        self::assertTrue($result->isAuthoritative());
        self::assertSame([], $result->getSkippedProductMessages());
        self::assertSame([], $result->getSkippedProductWarnings());
        self::assertSame(['SKU-1' => ['Existing attribute warning.']], $result->getProductWarnings());
    }

    public function testOmittedAttributePreservesSourceWarningWhileProductIsPrepared(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $warning = 'Product "T-2105": omitted attribute "product_type_pim"; ID 7788.';
        $attributes = $this->createStub(ProductAttributePublicationSourceInterface::class);
        $attributes->method('getValues')->willReturn(new ProductAttributeValuesResultDto([], [$warning]));
        $mapping = [
            'magento_attribute_code' => 'product_type_pim',
            'ergonode_attribute_code' => 'product_type',
        ];
        $source = new ProductSourceData(
            ['T-2105' => $product],
            [],
            [$mapping],
            [1 => 'template'],
            [1 => ['product_type']]
        );
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $state = $this->createStub(ProductStateInterface::class);
        $factory = $this->createStub(ProductDesiredStateFactoryInterface::class);
        $factory->method('createProduct')->willReturn($state);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            $attributes,
            $this->relationSourcePool(),
            $this->identityRegistry(),
            $this->attributePolicy()
        );
        $result = $builder->build($source);
        self::assertSame([$state], $result->getStates());
        self::assertSame(['T-2105' => [$warning]], $result->getProductWarnings());
    }

    public function testMappedModeUsesMagentoAttributeForErgonodeSku(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(31);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([]);
        $registry->expects(self::once())->method('getMappedSkuValuesByProductIds')->with([31])
            ->willReturn([31 => 'NAV-31']);
        $registry->expects(self::once())->method('findMappedSkuProductIds')->with(['NAV-31'])
            ->willReturn(['NAV-31' => 31]);
        $state = $this->createStub(ProductStateInterface::class);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createProduct')->with(
            'MAG-31',
            ProductStateInterface::TYPE_SIMPLE,
            'template',
            [],
            [],
            self::anything(),
            false,
            [],
            31,
            'NAV-31',
            ProductIdentityInterface::MODE_MAPPED
        )->willReturn($state);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->relationSourcePool(),
            $registry,
            $this->attributePolicy(ProductIdentityInterface::MODE_MAPPED)
        );

        $result = $builder->build(new ProductSourceData(
            ['MAG-31' => $product],
            [],
            [],
            [1 => 'template'],
            []
        ), ['MAG-31']);

        self::assertSame([$state], $result->getStates());
        self::assertSame([], $result->getSkippedProductMessages());
    }

    public function testMappedModeKeepsNumericMagentoSkuAsText(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(31);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([]);
        $registry->method('getMappedSkuValuesByProductIds')->willReturn([31 => '9001']);
        $registry->method('findMappedSkuProductIds')->willReturn(['9001' => 31]);
        $state = $this->createStub(ProductStateInterface::class);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createProduct')->with(
            '8001',
            ProductStateInterface::TYPE_SIMPLE,
            'template',
            [],
            [],
            self::anything(),
            false,
            [],
            31,
            '9001',
            ProductIdentityInterface::MODE_MAPPED
        )->willReturn($state);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->relationSourcePool(),
            $registry,
            $this->attributePolicy(ProductIdentityInterface::MODE_MAPPED)
        );

        $result = $builder->build(new ProductSourceData(
            ['8001' => $product],
            [],
            [],
            [1 => 'template'],
            []
        ), ['8001']);

        self::assertSame([$state], $result->getStates());
        self::assertTrue($result->isAuthoritative());
    }

    public function testMissingConfiguredMappedSkuStopsProductLocally(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(31);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('getMappedSkuValuesByProductIds')->willReturn([31 => '']);
        $registry->method('getMappedSkuAttributeCode')->willReturn('external_id');
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::never())->method('createProduct');
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->relationSourcePool(),
            $registry,
            $this->attributePolicy(ProductIdentityInterface::MODE_MAPPED)
        );

        $result = $builder->build(new ProductSourceData(
            ['MAG-31' => $product],
            [],
            [],
            [1 => 'template'],
            []
        ), ['MAG-31']);

        self::assertSame([], $result->getStates());
        self::assertStringContainsString('external_id', $result->getSkippedProductWarnings()['MAG-31']);
    }

    public function testHistoricalAssignedBindingWithDifferentConfiguredSkuRequiresMigration(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(31);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_ASSIGNED);
        $identity->method('getErgonodeSku')->willReturn('OLD-NATIVE');
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([31 => $identity]);
        $registry->method('getMappedSkuValuesByProductIds')->willReturn([31 => 'NEW-NATIVE']);
        $registry->method('findMappedSkuProductIds')->willReturn(['NEW-NATIVE' => 31]);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::never())->method('createProduct');
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->relationSourcePool(),
            $registry,
            $this->attributePolicy(ProductIdentityInterface::MODE_MAPPED)
        );

        $result = $builder->build(new ProductSourceData(
            ['MAG-31' => $product],
            [],
            [],
            [1 => 'template'],
            []
        ), ['MAG-31']);

        self::assertSame([], $result->getStates());
        self::assertStringContainsString(
            'Reconcile or migrate',
            $result->getSkippedProductWarnings()['MAG-31']
        );
    }

    /**
     * @param array<array<string, string>> $mappings
     * @param string[] $templateAttributes
     */
    #[DataProvider('unpublishableAssignedSku')]
    public function testAssignedModeSkipsCreationWithoutPublishableMagentoSkuValue(
        array $mappings,
        array $templateAttributes,
        string $expectedMessage
    ): void {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::never())->method('createProduct');
        $attributes = $this->createStub(ProductAttributePublicationSourceInterface::class);
        $attributes->method('getValues')->willReturn(new ProductAttributeValuesResultDto([]));
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            $attributes,
            $this->relationSourcePool(),
            $this->identityRegistry(),
            $this->attributePolicy(ProductIdentityInterface::MODE_ASSIGNED)
        );

        $result = $builder->build(new ProductSourceData(
            ['T-2105' => $product],
            [],
            $mappings,
            [1 => 'template'],
            [1 => $templateAttributes]
        ), ['T-2105']);

        self::assertSame([], $result->getStates());
        self::assertStringContainsString(
            $expectedMessage,
            $result->getSkippedProductWarnings()['T-2105']
        );
    }

    /** @return array<string, array{array<array<string, string>>, string[], string}> */
    public static function unpublishableAssignedSku(): array
    {
        return [
            'missing template mapping' => [[], [], 'requires a publishable Magento sku mapping'],
            'invalid remote SKU attribute' => [[[
                'magento_attribute_code' => 'sku',
                'ergonode_attribute_code' => 'magento_sku',
                'publication_error' => 'Remote attribute is not unique Global Text.',
            ]], ['magento_sku'], 'requires a publishable Magento sku mapping'],
            'missing value' => [[[
                'magento_attribute_code' => 'sku',
                'ergonode_attribute_code' => 'magento_sku',
            ]], ['magento_sku'], 'no matching publishable value'],
        ];
    }

    public function testAssignedModePreparesProductWithMappedMagentoSkuValue(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $value = new ProductAttributeValue('magento_sku', 'text', ['en_GB' => 'T-2105']);
        $attributes = $this->createStub(ProductAttributePublicationSourceInterface::class);
        $attributes->method('getValues')->willReturn(new ProductAttributeValuesResultDto([$value]));
        $state = $this->createStub(ProductStateInterface::class);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createProduct')->willReturn($state);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            $attributes,
            $this->relationSourcePool(),
            $this->identityRegistry(),
            $this->attributePolicy(ProductIdentityInterface::MODE_ASSIGNED)
        );

        $result = $builder->build(new ProductSourceData(
            ['T-2105' => $product],
            [],
            [[
                'magento_attribute_code' => 'sku',
                'ergonode_attribute_code' => 'magento_sku',
            ]],
            [1 => 'template'],
            [1 => ['magento_sku']]
        ), ['T-2105']);

        self::assertSame([$state], $result->getStates());
    }

    public function testUnrelatedUnsupportedProductDoesNotInvalidateSelectedScope(): void
    {
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturnCallback(
            static fn (string $type): string => $type === 'simple'
                ? ProductStateInterface::TYPE_SIMPLE
                : throw new LocalizedException(__('Unsupported type.'))
        );
        $supported = $this->createStub(Product::class);
        $supported->method('getTypeId')->willReturn('simple');
        $supported->method('getAttributeSetId')->willReturn(1);
        $supported->method('getCategoryIds')->willReturn([]);
        $supported->method('getStatus')->willReturn(1);
        $unsupported = $this->createStub(Product::class);
        $unsupported->method('getTypeId')->willReturn('bundle');
        $stateFactory = $this->createStub(ProductDesiredStateFactoryInterface::class);
        $stateFactory->method('createRelations')->willReturn(
            $this->createStub(ProductRelationStateInterface::class)
        );
        $stateFactory->method('createProduct')->willReturn($this->createStub(ProductStateInterface::class));
        $relationSourcePool = $this->relationSourcePool();
        $source = new ProductSourceData(
            ['SKU-1' => $supported, 'UNSUPPORTED' => $unsupported],
            [],
            [],
            [1 => 'template'],
            []
        );
        $builder = new ProductSourceStateBuilder(
            $stateFactory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $relationSourcePool,
            $this->identityRegistry(),
            $this->attributePolicy()
        );

        self::assertTrue($builder->build($source, ['SKU-1'])->isAuthoritative());
        $unsupportedResult = $builder->build($source, ['UNSUPPORTED']);
        self::assertFalse($unsupportedResult->isAuthoritative());
        self::assertSame(
            'Unsupported type.',
            $unsupportedResult->getSkippedProductWarnings()['UNSUPPORTED'] ?? null
        );
        self::assertFalse($builder->build($source)->isAuthoritative());
    }

    public function testLeavesErgonodeWorkflowStatusUnmanaged(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $product->method('getCategoryIds')->willReturn([]);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $relations = $this->createStub(ProductRelationStateInterface::class);
        $expected = $this->createStub(ProductStateInterface::class);
        $stateFactory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $stateFactory->method('createRelations')->willReturn($relations);
        $stateFactory->expects(self::once())->method('createProduct')->with(
            'SKU-1',
            ProductStateInterface::TYPE_SIMPLE,
            'template',
            [],
            [],
            $relations,
            false,
            [],
            null,
            'SKU-1',
            'shared'
        )->willReturn($expected);
        $relationResult = $this->createStub(ProductRelationSourceResultInterface::class);
        $relationResult->method('getRelations')->willReturn($relations);
        $relationResult->method('getAuthoritativeCollections')->willReturn([]);
        $relationSourcePool = $this->createStub(ProductRelationSourcePoolInterface::class);
        $relationSourcePool->method('extract')->willReturn($relationResult);
        $builder = new ProductSourceStateBuilder(
            $stateFactory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $relationSourcePool,
            $this->identityRegistry(),
            $this->attributePolicy()
        );

        $result = $builder->build(new ProductSourceData(
            ['SKU-1' => $product],
            [],
            [],
            [1 => 'template'],
            []
        ));

        self::assertSame([$expected], $result->getStates());
    }

    /** Verify that the source result explains why an unmapped product was omitted. */
    public function testReportsProductSkippedBecauseItsAttributeSetHasNoTemplateMapping(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(7);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $builder = new ProductSourceStateBuilder(
            $this->createStub(ProductDesiredStateFactoryInterface::class),
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->createStub(ProductRelationSourcePoolInterface::class),
            $this->identityRegistry(),
            $this->attributePolicy()
        );

        $result = $builder->build(new ProductSourceData(
            ['SKU-7' => $product],
            [],
            [],
            [],
            []
        ));

        self::assertSame([], $result->getStates());
        self::assertFalse($result->isAuthoritative());
        self::assertSame([
            'SKU-7' => 'Skipped Magento product "SKU-7" because attribute set ID 7 has no Ergonode template mapping.',
        ], $result->getSkippedProductWarnings());
    }

    public function testAssignedSkuProductSkipsWhenTemplateDoesNotContainMappedSkuAttribute(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getAttributeSetId')->willReturn(1);
        $product->method('getCategoryIds')->willReturn([]);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $relations = $this->createStub(ProductRelationStateInterface::class);
        $relationResult = $this->createStub(ProductRelationSourceResultInterface::class);
        $relationResult->method('getRelations')->willReturn($relations);
        $relationResult->method('getAuthoritativeCollections')->willReturn([]);
        $relationSourcePool = $this->createStub(ProductRelationSourcePoolInterface::class);
        $relationSourcePool->method('extract')->willReturn($relationResult);
        $stateFactory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::never())->method('createValue');
        $stateFactory->expects(self::never())->method('createProduct');
        $builder = new ProductSourceStateBuilder(
            $stateFactory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $relationSourcePool,
            $this->identityRegistry(),
            $this->attributePolicy(ProductIdentityModeProviderInterface::MODE_ASSIGNED)
        );

        $result = $builder->build(new ProductSourceData(
            ['SKU-1' => $product],
            [],
            [[
                'magento_attribute_code' => 'sku',
                'ergonode_attribute_code' => 'product_sku',
                'ergonode_type' => 'text',
                'option_ids' => [],
            ]],
            [1 => 'bag'],
            [1 => []]
        ), ['SKU-1']);

        self::assertSame([], $result->getStates());
        self::assertFalse($result->isAuthoritative());
        self::assertStringContainsString(
            'requires a publishable Magento sku mapping',
            $result->getSkippedProductWarnings()['SKU-1']
        );
    }

    public function testMissingValueModulePreparesProductsWithoutCompletenessWarnings(): void
    {
        $products = [];
        foreach (['BAD' => 1, 'GOOD' => 2] as $sku => $setId) {
            $product = $this->createStub(Product::class);
            $product->method('getAttributeSetId')->willReturn($setId);
            $product->method('getTypeId')->willReturn('simple');
            $products[$sku] = $product;
        }
        $expected = $this->createStub(ProductStateInterface::class);
        $factory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $factory->expects(self::exactly(2))->method('createProduct')->willReturn($expected);
        $typeMapper = $this->createStub(ProductTypeMapperInterface::class);
        $typeMapper->method('map')->willReturn(ProductStateInterface::TYPE_SIMPLE);
        $builder = new ProductSourceStateBuilder(
            $factory,
            $typeMapper,
            new EmptyProductAttributePublicationSource(),
            $this->relationSourcePool(),
            $this->identityRegistry(),
            $this->attributePolicy()
        );
        $result = $builder->build(new ProductSourceData(
            $products,
            [],
            [],
            [1 => 'required', 2 => 'optional'],
            []
        ));
        self::assertSame([$expected, $expected], $result->getStates());
        self::assertTrue($result->isAuthoritative());
        self::assertSame([], $result->getSkippedProductMessages());
        self::assertSame([], $result->getProductWarnings());
    }

    private function relationSourcePool(): ProductRelationSourcePoolInterface
    {
        $relations = $this->createStub(ProductRelationStateInterface::class);
        $result = $this->createStub(ProductRelationSourceResultInterface::class);
        $result->method('getRelations')->willReturn($relations);
        $result->method('getAuthoritativeCollections')->willReturn([]);
        $pool = $this->createStub(ProductRelationSourcePoolInterface::class);
        $pool->method('extract')->willReturn($result);

        return $pool;
    }

    private function identityRegistry(): ProductIdentityRegistryInterface
    {
        $registry = $this->createStub(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([]);

        return $registry;
    }

    private function attributePolicy(
        string $skuMode = ProductIdentityModeProviderInterface::MODE_SHARED
    ): ProductIdentityModeProviderInterface {
        $policy = $this->createStub(ProductIdentityModeProviderInterface::class);
        $policy->method('getMode')->willReturn($skuMode);

        return $policy;
    }
}
