<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Source;

use Ergonode\AttributePublisher\Api\AttributeDesiredStateFactoryInterface;
use Ergonode\ProductAttributePublisher\Api\AttributeSourceConfigurationInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\ProductAttributePublisher\Model\Source\AttributeSourceStateBuilder;
use Ergonode\ProductAttributePublisher\Model\Source\OptionSourceStateBuilder;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Api\UnitAttributeMetadataInterface;

class AttributeSourceStateBuilderTest extends TestCase
{
    public function testBuildsMagentoSkuAsUniqueGlobalTextIdentifier(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getScope')->willReturn(ProductAttributeInterface::SCOPE_STORE_TEXT);
        $attribute->method('getIsUnique')->willReturn(false);
        $attribute->method('getDefaultFrontendLabel')->willReturn('SKU');
        $attribute->method('getFrontendLabels')->willReturn([]);
        $repository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with('sku')->willReturn($attribute);
        $projection = $this->createStub(LocalizedStoreProjection::class);
        $projection->method('project')->willReturn(['en_GB' => 'SKU']);
        $expected = $this->createStub(AttributeStateInterface::class);
        $factory = $this->createMock(AttributeDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createAttribute')->with(
            'magento_sku',
            'text',
            'GLOBAL',
            ['en_GB' => 'SKU'],
            ['unique' => true],
            [],
            []
        )->willReturn($expected);

        $result = (new AttributeSourceStateBuilder(
            $repository,
            $factory,
            $projection,
            $this->createStub(AttributeSourceConfigurationInterface::class),
            $this->createStub(UnitAttributeMetadataInterface::class),
            $this->createStub(OptionSourceStateBuilder::class)
        ))->build('magento_sku', 'sku', 'textarea', []);

        self::assertSame($expected, $result);
    }

    public function testBuildsLocalizedPriceStateFromMagentoDefinition(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getScope')->willReturn(ProductAttributeInterface::SCOPE_GLOBAL_TEXT);
        $attribute->method('getDefaultFrontendLabel')->willReturn('Price');
        $attribute->method('getFrontendLabels')->willReturn([]);
        $repository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->with('rrp')->willReturn($attribute);
        $projection = $this->createStub(LocalizedStoreProjection::class);
        $projection->method('project')->willReturn(['en_GB' => 'Price']);
        $configuration = $this->createStub(AttributeSourceConfigurationInterface::class);
        $configuration->method('getPriceCurrency')->willReturn('PLN');
        $expected = $this->createStub(AttributeStateInterface::class);
        $factory = $this->createMock(AttributeDesiredStateFactoryInterface::class);
        $factory->expects(self::once())->method('createAttribute')->with(
            'rrp',
            'price',
            'GLOBAL',
            ['en_GB' => 'Price'],
            ['currency' => 'PLN'],
            [],
            []
        )->willReturn($expected);

        $result = (new AttributeSourceStateBuilder(
            $repository,
            $factory,
            $projection,
            $configuration,
            $this->createStub(UnitAttributeMetadataInterface::class),
            $this->createStub(OptionSourceStateBuilder::class)
        ))->build('rrp', 'rrp', 'price', []);

        self::assertSame($expected, $result);
    }

    public function testDelegatesAllOptionsIncludingZeroToSharedBuilder(): void
    {
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getScope')->willReturn(ProductAttributeInterface::SCOPE_GLOBAL_TEXT);
        $repository = $this->createStub(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->willReturn($attribute);
        $option = $this->createStub(AttributeOptionStateInterface::class);
        $options = $this->createMock(OptionSourceStateBuilder::class);
        $options->expects(self::once())->method('build')->with($attribute, 'enabled', [0, 1])->willReturn([$option]);
        $factory = $this->createMock(AttributeDesiredStateFactoryInterface::class);
        $state = $this->createStub(AttributeStateInterface::class);
        $factory->expects(self::once())->method('createAttribute')->with(
            'enabled',
            'select',
            'GLOBAL',
            [],
            [],
            [],
            [$option]
        )->willReturn($state);

        self::assertSame($state, (new AttributeSourceStateBuilder(
            $repository,
            $factory,
            $this->createStub(LocalizedStoreProjection::class),
            $this->createStub(AttributeSourceConfigurationInterface::class),
            $this->createStub(UnitAttributeMetadataInterface::class),
            $options
        ))->build('enabled', 'enabled', 'select', [0, 1]));
    }
}
