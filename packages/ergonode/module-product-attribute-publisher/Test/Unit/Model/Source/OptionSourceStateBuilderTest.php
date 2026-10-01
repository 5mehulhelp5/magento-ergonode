<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Source;

use Ergonode\AttributePublisher\Model\Data\AttributeDesiredStateFactory;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Model\LocalizedStoreProjection;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributePublisher\Model\Source\OptionCodeGenerator;
use Ergonode\ProductAttributePublisher\Model\Source\OptionSourceStateBuilder;
use Ergonode\ProductAttributePublisher\Model\Source\SystemOptionDefinition;
use Ergonode\ProductAttributePublisher\Model\Source\SystemOptionDictionary;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\TranslitUrl;
use PHPUnit\Framework\TestCase;

class OptionSourceStateBuilderTest extends TestCase
{
    public function testBooleanZeroUsesDictionaryAndEnglishFallbackWithoutChangingLanguageCode(): void
    {
        $attribute = $this->attribute('is_enabled', ['0' => 'Nein', '1' => 'Ja'], 'boolean');
        $options = $this->builder()->build($attribute, 'is_enabled', [0, 1]);

        self::assertSame(['no', 'yes'], array_map(static fn ($option) => $option->getCode(), $options));
        self::assertSame(['de_AT' => 'Nein', 'ja_JP' => 'No', 'pl_PL' => 'Nie'], $options[0]->getNames());
        self::assertSame(['de_AT' => 'Ja', 'ja_JP' => 'Yes', 'pl_PL' => 'Tak'], $options[1]->getNames());
    }

    public function testBooleanSourceOnSelectInputIsRecognized(): void
    {
        $attribute = $this->attribute('is_featured', ['0' => 'No'], 'select', Boolean::class);
        $option = $this->builder()->build($attribute, 'featured', [0])[0];

        self::assertSame('Nie', $option->getNames()['pl_PL']);
    }

    public function testStatusTwoMeansNoAndKeepsCanonicalDisabledCode(): void
    {
        $attribute = $this->attribute('status', ['1' => 'Enabled', '2' => 'Disabled']);
        $options = $this->builder()->build($attribute, 'status', [1, 2]);

        self::assertSame('enabled', $options[0]->getCode());
        self::assertSame('Tak', $options[0]->getNames()['pl_PL']);
        self::assertSame('disabled', $options[1]->getCode());
        self::assertSame('Nie', $options[1]->getNames()['pl_PL']);
    }

    public function testVisibilityPublishesAllFourMagentoMeanings(): void
    {
        $attribute = $this->attribute('visibility', [1 => 'Invisible', 2 => 'Catalog', 3 => 'Search', 4 => 'Both']);
        $options = $this->builder()->build($attribute, 'visibility', [1, 2, 3, 4]);

        self::assertSame(
            ['not_visible_individually', 'catalog', 'search', 'catalog_search'],
            array_map(static fn ($option) => $option->getCode(), $options)
        );
        self::assertSame(
            ['Niewidoczny pojedynczo', 'Katalog', 'Wyszukiwanie', 'Katalog, wyszukiwanie'],
            array_map(static fn ($option) => $option->getNames()['pl_PL'], $options)
        );
        self::assertSame('Not Visible Individually', $options[0]->getNames()['ja_JP']);
    }

    public function testSavedOptionCodeWinsOverGeneratedCodeAndExplicitCodeWinsOverMapping(): void
    {
        $mappings = $this->createStub(MappingReaderInterface::class);
        $mappings->method('getAttributeRows')->willReturn([
            ['mapping_id' => 7, 'magento_attribute_code' => 'enabled', 'ergonode_attribute_code' => 'enabled'],
        ]);
        $mappings->method('getOptionRows')->willReturn([
            ['magento_option_id' => 0, 'ergonode_option_code' => 'legacy_false'],
        ]);
        $attribute = $this->attribute('enabled', [0 => 'No'], 'boolean');
        $builder = $this->builder($mappings);

        self::assertSame('legacy_false', $builder->build($attribute, 'enabled', [0])[0]->getCode());
        self::assertSame('explicit_no', $builder->build($attribute, 'enabled', ['explicit_no' => 0])[0]->getCode());
        self::assertSame('no', $builder->build($attribute, 'another_attribute', [0])[0]->getCode());
    }

    public function testOrdinaryOptionKeepsItsOwnLabelsInsteadOfYesNoDictionary(): void
    {
        $label = $this->createStub(AttributeOptionLabelInterface::class);
        $label->method('getStoreId')->willReturn(1);
        $label->method('getLabel')->willReturn('Niebieski');
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn('12');
        $option->method('getLabel')->willReturn('Blue');
        $option->method('getStoreLabels')->willReturn([$label]);
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeCode')->willReturn('color');
        $attribute->method('getOptions')->willReturn([$option]);
        $state = $this->builder()->build($attribute, 'color', [12])[0];

        self::assertSame('blue', $state->getCode());
        self::assertSame('Niebieski', $state->getNames()['pl_PL']);
        self::assertSame('Blue', $state->getNames()['de_AT']);
    }

    public function testDuplicateGeneratedCodeIsRejectedEvenWhenOptionsArePreparedSeparately(): void
    {
        $attribute = $this->attribute('manufacturer', [
            17 => 'Zwiesel Glas',
            6755 => 'Zwiesel Glas',
            77 => 'Other Brand',
        ]);
        $builder = $this->builder();

        self::assertSame('other_brand', $builder->build($attribute, 'manufacturer', [77])[0]->getCode());
        foreach ([17, 6755] as $optionId) {
            try {
                $builder->build($attribute, 'manufacturer', [$optionId]);
                self::fail('Duplicate generated code should be rejected for option ' . $optionId);
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('"zwiesel_glas"', $exception->getMessage());
            }
        }
    }

    public function testSavedCodeReservesItsCodeButDoesNotChangeAnExplicitChoice(): void
    {
        $mappings = $this->createStub(MappingReaderInterface::class);
        $mappings->method('getAttributeRows')->willReturn([
            ['mapping_id' => 7, 'magento_attribute_code' => 'manufacturer',
                'ergonode_attribute_code' => 'manufacturer'],
        ]);
        $mappings->method('getOptionRows')->willReturn([
            ['magento_option_id' => 17, 'ergonode_option_code' => 'zwiesel_glas'],
        ]);
        $attribute = $this->attribute('manufacturer', [17 => 'Zwiesel Glas', 6755 => 'Zwiesel Glas']);
        $builder = $this->builder($mappings);

        self::assertSame('zwiesel_glas', $builder->build($attribute, 'manufacturer', [17])[0]->getCode());
        self::assertSame('chosen_code', $builder->build($attribute, 'manufacturer', ['chosen_code' => 6755])[0]
            ->getCode());
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('"zwiesel_glas"');
        $builder->build($attribute, 'manufacturer', [6755]);
    }

    public function testMissingOptionIsRejectedInsteadOfInventingAnOption(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento option ID "2"');
        $this->builder()->build($this->attribute('is_enabled', [0 => 'No'], 'boolean'), 'enabled', [2]);
    }

    private function builder(?MappingReaderInterface $mappings = null): OptionSourceStateBuilder
    {
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL', 'de_AT', 'ja_JP']);
        $languages->method('getLanguageStoreMap')->willReturn([1 => 'pl_PL', 2 => 'de_AT', 3 => 'ja_JP']);

        return new OptionSourceStateBuilder(
            new AttributeDesiredStateFactory(),
            new LocalizedStoreProjection($languages),
            new SystemOptionDefinition(new SystemOptionDictionary(), $languages),
            new OptionCodeGenerator(new TranslitUrl($this->createStub(ScopeConfigInterface::class))),
            $mappings ?? $this->createStub(MappingReaderInterface::class)
        );
    }

    /** @param array<int|string, string> $labels */
    private function attribute(
        string $code,
        array $labels,
        string $input = 'select',
        string $source = ''
    ): ProductAttributeInterface {
        $options = [];
        foreach ($labels as $value => $label) {
            $option = $this->createStub(AttributeOptionInterface::class);
            $option->method('getValue')->willReturn((string)$value);
            $option->method('getLabel')->willReturn($label);
            $options[] = $option;
        }
        $attribute = $this->createStub(ProductAttributeInterface::class);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getFrontendInput')->willReturn($input);
        $attribute->method('getSourceModel')->willReturn($source);
        $attribute->method('getOptions')->willReturn($options);

        return $attribute;
    }
}
