<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\Source;

use Ergonode\Attribute\Model\MagentoAttributeTypeResolver;
use Ergonode\AttributePublisher\Model\AttributeTypeResolver;
use Ergonode\AttributePublisher\Model\Data\AttributeDesiredStateFactory;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Ergonode\CategoryAttributePublisher\Model\Provider\CategoryAttributeSourceStateBuilder;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\CategoryAttributePublisher\Api\MagentoOptionLabelReaderInterface;
use Magento\Eav\Model\Config as EavConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CategoryAttributeSourceStateBuilderTest extends TestCase
{
    public function testBuildsSelectStateWithStableMagentoOptionCodes(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(12);
        $attribute->method('getAttributeCode')->willReturn('is_featured');
        $attribute->method('getFrontendInput')->willReturn('boolean');
        $attribute->method('getBackendType')->willReturn('int');
        $attribute->method('getScope')->willReturn(Attribute::SCOPE_GLOBAL_TEXT);
        $attribute->method('getDefaultFrontendLabel')->willReturn('Featured');
        $attribute->method('getFrontendLabels')->willReturn([]);
        $attribute->method('getOptions')->willReturn([
            $this->option('0', 'No'),
            $this->option('1', 'Yes'),
        ]);
        $projection = $this->createStub(LocalizedStoreProjection::class);
        $projection->method('project')->willReturnCallback(
            static fn (string $value): array => ['en_US' => $value]
        );

        $state = $this->builder($attribute, $projection)->build('is_featured', 'select');

        self::assertSame('select', $state->getType());
        self::assertSame('GLOBAL', $state->getScope());
        self::assertSame(['en_US' => 'Featured'], $state->getNames());
        self::assertSame(['option_0', 'option_1'], array_map(
            static fn ($option): string => $option->getCode(),
            $state->getOptions()
        ));
    }

    public function testMapsMagentoDecimalToErgonodeNumeric(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(13);
        $attribute->method('getFrontendInput')->willReturn('text');
        $attribute->method('getBackendType')->willReturn('decimal');
        $attribute->method('getScope')->willReturn(Attribute::SCOPE_STORE_TEXT);
        $attribute->method('getDefaultFrontendLabel')->willReturn('Score');
        $attribute->method('getFrontendLabels')->willReturn([]);
        $attribute->method('getIsUnique')->willReturn(false);
        $projection = $this->createStub(LocalizedStoreProjection::class);
        $projection->method('project')->willReturn(['en_US' => 'Score']);

        $state = $this->builder($attribute, $projection)->build('score', 'numeric');

        self::assertSame('numeric', $state->getType());
        self::assertSame('LOCAL', $state->getScope());
        self::assertSame(['unique' => false], $state->getParameters());
    }

    public function testPublishesTranslatedOptionLabelsAndFallsBackForUntranslatedStores(): void
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(12);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getScope')->willReturn(Attribute::SCOPE_STORE_TEXT);
        $attribute->method('getDefaultFrontendLabel')->willReturn('Color');
        $attribute->method('getFrontendLabels')->willReturn([]);
        $label = $this->createStub(AttributeOptionLabelInterface::class);
        $label->method('getStoreId')->willReturn(2);
        $label->method('getLabel')->willReturn('Czerwony');
        $option = $this->option('12', 'Red');
        $option->method('getStoreLabels')->willReturn([$label]);
        $attribute->method('getOptions')->willReturn([$option]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([1 => 'en_US', 2 => 'pl_PL', 3 => 'de_DE']);

        $state = $this->builder($attribute, new LocalizedStoreProjection($languages))->build('color', 'select');

        self::assertSame(
            ['de_DE' => 'Red', 'en_US' => 'Red', 'pl_PL' => 'Czerwony'],
            $state->getOptions()[0]->getNames()
        );
    }

    private function builder(
        Attribute $attribute,
        LocalizedStoreProjection $projection
    ): CategoryAttributeSourceStateBuilder {
        $eavConfig = $this->createStub(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);

        return new CategoryAttributeSourceStateBuilder(
            $eavConfig,
            new AttributeDesiredStateFactory(),
            new AttributeTypeResolver(new MagentoAttributeTypeResolver()),
            $projection,
            $this->createStub(MagentoOptionLabelReaderInterface::class)
        );
    }

    private function option(string $value, string $label): AttributeOptionInterface&Stub
    {
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn($value);
        $option->method('getLabel')->willReturn($label);

        return $option;
    }
}
