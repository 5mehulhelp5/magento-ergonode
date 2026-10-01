<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Source;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\AttributePublisher\Api\AttributeStateLoaderInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use PHPUnit\Framework\Attributes\DataProvider;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Api\SkuIdentityMappingProviderInterface;
use Ergonode\ProductAttributePublisher\Model\Source\ProductAttributePublicationSource;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueClearIntentInterface;
use Ergonode\ProductPublisher\Api\ProductAttributeSourceValidatorInterface;
use Ergonode\ProductPublisher\Model\Data\ProductDesiredStateFactory;
use Ergonode\ProductPublisher\Model\Source\ProductAttributeSourceValidatorPool;
use Ergonode\ProductPublisher\Model\Source\ProductAttributeValueResolverPool;
use Ergonode\ProductPublisher\Model\Source\ProductValueNormalizer;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductAttributePublicationSourceTest extends TestCase
{
    public function testMapsMagentoOptionsAndKeepsExplicitTranslationClearIntent(): void
    {
        $source = $this->source([3 => 'en_GB', 1 => 'pl_PL']);
        $product = $this->product('SKU-1', 42);

        $result = $source->getValues($product, [
            3 => ['SKU-1' => $product],
            1 => ['SKU-1' => $this->product('SKU-1', null)],
        ], [[
            'magento_attribute_code' => 'color',
            'ergonode_attribute_code' => 'colour',
            'ergonode_type' => 'select',
            'option_ids' => ['blue' => 42],
        ]]);

        $values = $result->getValues();
        self::assertCount(1, $values);
        self::assertSame([], $result->getWarnings());
        self::assertSame('colour', $values[0]->getAttributeCode());
        self::assertSame(['en_GB' => 'blue'], $values[0]->getTranslations());
        self::assertInstanceOf(
            ProductAttributeValueClearIntentInterface::class,
            $values[0]
        );
        self::assertSame(['pl_PL'], $values[0]->getClearedLanguageCodes());
    }

    public function testPassesOriginalSelectedStoreValuesToValidators(): void
    {
        $mapping = [
            'magento_attribute_code' => 'color',
            'ergonode_attribute_code' => 'colour',
            'ergonode_type' => 'select',
            'option_ids' => ['blue' => 42, 'red' => 87],
        ];
        $product = $this->product('SKU-1', 87);
        $validator = $this->createMock(ProductAttributeSourceValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->with(
            $product,
            $mapping,
            ['en_GB' => 'blue'],
            ['en_GB' => 42]
        );
        $source = $this->source([0 => 'en_GB', 3 => 'en_GB', 1 => 'en_GB', 2 => 'pl_PL'], validators: [$validator]);
        $source->getValues($product, [
            0 => ['SKU-1' => $product],
            3 => ['SKU-1' => $this->product('SKU-1', 42)],
            1 => ['SKU-1' => $product],
            2 => ['SKU-1' => $this->product('SKU-1', null)],
        ], [$mapping]);
    }

    public function testMissingMultiSelectOptionOmitsAllLanguagesAndClearIntent(): void
    {
        $source = $this->source([0 => 'en_GB', 1 => 'en_GB', 2 => 'pl_PL']);
        $mapping = [
            'magento_attribute_code' => 'product_type_pim',
            'ergonode_attribute_code' => 'product_type',
            'ergonode_type' => 'multi_select',
            'option_ids' => ['known' => 7],
        ];
        $result = $source->getValues($this->product('T-2105', null), [
            1 => ['T-2105' => $this->product('T-2105', '7,7788,1')],
            2 => ['T-2105' => $this->product('T-2105', null)],
        ], [$mapping]);

        self::assertSame([], $result->getValues());
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('T-2105', $result->getWarnings()[0]);
        self::assertStringContainsString('product_type_pim', $result->getWarnings()[0]);
        self::assertStringContainsString('1, 7788', $result->getWarnings()[0]);
    }

    public function testT2105ReportsTwoUnmappedAttributesOnceAcrossLanguagesAndKeepsValidValue(): void
    {
        $source = $this->source([1 => 'en_GB', 2 => 'pl_PL']);
        $mappings = [
            [
                'magento_attribute_code' => 'product_type_pim',
                'ergonode_attribute_code' => 'product_type',
                'ergonode_type' => 'select',
                'option_ids' => [],
            ],
            [
                'magento_attribute_code' => 'navireo_synchro',
                'ergonode_attribute_code' => 'navireo_sync',
                'ergonode_type' => 'select',
                'option_ids' => [],
            ],
            [
                'magento_attribute_code' => 'name',
                'ergonode_attribute_code' => 'title',
                'ergonode_type' => 'text',
                'option_ids' => [],
            ],
        ];
        $storeProduct = $this->productWithData('T-2105', [
            'product_type_pim' => 7788,
            'navireo_synchro' => 1,
            'name' => 'Desk',
        ]);
        $result = $source->getValues($storeProduct, [
            1 => ['T-2105' => $storeProduct],
            2 => ['T-2105' => $storeProduct],
        ], $mappings);

        self::assertSame(
            ['title'],
            array_map(static fn ($value): string => $value->getAttributeCode(), $result->getValues())
        );
        self::assertCount(2, $result->getWarnings());
        self::assertStringContainsString('product_type_pim', $result->getWarnings()[0]);
        self::assertStringContainsString('7788', $result->getWarnings()[0]);
        self::assertStringContainsString('navireo_synchro', $result->getWarnings()[1]);
        self::assertStringContainsString('1', $result->getWarnings()[1]);
    }

    public function testDifferentMissingIdsAcrossLanguagesAreCollectedOncePerAttribute(): void
    {
        $mapping = [
            'magento_attribute_code' => 'color',
            'ergonode_attribute_code' => 'colour',
            'ergonode_type' => 'select',
            'option_ids' => [],
        ];
        $result = $this->source([1 => 'en_GB', 2 => 'pl_PL', 3 => 'de_DE'])->getValues(
            $this->product('SKU-1', null),
            [
                1 => ['SKU-1' => $this->product('SKU-1', 7788)],
                2 => ['SKU-1' => $this->product('SKU-1', 1)],
                3 => ['SKU-1' => $this->product('SKU-1', 7788)],
            ],
            [$mapping]
        );

        self::assertSame([], $result->getValues());
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('1, 7788', $result->getWarnings()[0]);
    }

    public function testOtherLocalValidationErrorOmitsOnlyItsAttribute(): void
    {
        $product = $this->product('SKU-1', 'invalid');
        $result = $this->source([1 => 'en_GB'])->getValues($product, [1 => ['SKU-1' => $product]], [[
            'magento_attribute_code' => 'weight',
            'ergonode_attribute_code' => 'weight',
            'ergonode_type' => 'numeric',
            'option_ids' => [],
        ]]);
        self::assertSame([], $result->getValues());
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('weight', $result->getWarnings()[0]);
        self::assertStringContainsString('must be a finite number', $result->getWarnings()[0]);
    }

    public function testUnavailablePublicationAdapterWarnsAndKeepsAnotherAttribute(): void
    {
        $product = $this->productWithData('SKU-1', ['name' => 'Desk']);
        $result = $this->source([1 => 'en_GB'])->getValues($product, [1 => ['SKU-1' => $product]], [
            [
                'magento_attribute_code' => 'color',
                'ergonode_attribute_code' => 'colour',
                'publication_error' => 'Publication adapter is unavailable.',
            ],
            [
                'magento_attribute_code' => 'name',
                'ergonode_attribute_code' => 'title',
                'ergonode_type' => 'text',
                'option_ids' => [],
            ],
        ]);

        self::assertSame(['title'], array_map(
            static fn ($value): string => $value->getAttributeCode(),
            $result->getValues()
        ));
        self::assertCount(1, $result->getWarnings());
        self::assertStringContainsString('color', $result->getWarnings()[0]);
        self::assertStringContainsString('Publication adapter is unavailable.', $result->getWarnings()[0]);
    }

    /**
     * @param array<int, string> $stores
     * @param array<string, string> $expected
     * @param string[] $cleared
     */
    #[DataProvider('publicationStores')]
    public function testUsesFirstMappedStoreAndNeverPublishesAdminDirectly(
        array $stores,
        ?string $englishValue,
        array $expected,
        array $cleared
    ): void {
        $source = $this->source($stores);
        $result = $source->getValues($this->product('SKU-1', 'Admin name'), [
            1 => ['SKU-1' => $this->product('SKU-1', 'Admin name')],
            3 => ['SKU-1' => $this->product('SKU-1', $englishValue)],
        ], [[
            'magento_attribute_code' => 'name',
            'ergonode_attribute_code' => 'title',
            'ergonode_type' => 'text',
            'option_ids' => [],
        ]]);

        $values = $result->getValues();
        self::assertSame($expected, $values[0]->getTranslations());
        self::assertInstanceOf(ProductAttributeValueClearIntentInterface::class, $values[0]);
        self::assertSame($cleared, $values[0]->getClearedLanguageCodes());
    }

    /** @return array<string, array{array<int, string>, string|null, array<string, string>, string[]}> */
    public static function publicationStores(): array
    {
        return [
            'admin does not compete with store override' => [
                [0 => 'en_GB', 3 => 'en_GB', 1 => 'en_GB'], 'English name', ['en_GB' => 'English name'], [],
            ],
            'mapping order determines the source' => [
                [1 => 'en_GB', 3 => 'en_GB', 0 => 'en_GB'], 'English name', ['en_GB' => 'Admin name'], [],
            ],
            'inherited value comes from loaded store product' => [
                [0 => 'en_GB', 3 => 'en_GB'], 'Admin name', ['en_GB' => 'Admin name'], [],
            ],
            'empty source is not replaced by another store' => [
                [0 => 'en_GB', 3 => 'en_GB', 1 => 'en_GB'], null, [], ['en_GB'],
            ],
            'admin-only mapping supplies no outbound values' => [
                [0 => 'en_GB'], 'English name', [], [],
            ],
            'admin language is not exported separately' => [
                [0 => 'pl_PL', 3 => 'en_GB'], 'English name', ['en_GB' => 'English name'], [],
            ],
        ];
    }

    #[DataProvider('skuDefinitions')]
    public function testSkuMappingMarksInvalidRemoteConstraintsWithoutGlobalFailure(
        ?AttributeState $definition,
        bool $valid
    ): void {
        $mapping = [
            'mapping_id' => 7,
            'magento_attribute_code' => 'sku',
            'ergonode_attribute_code' => 'magento_sku',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'option_ids' => [],
        ];
        $skuMappings = $this->createStub(SkuIdentityMappingProviderInterface::class);
        $skuMappings->method('getMapping')->willReturn($mapping);
        $loader = $this->createMock(AttributeStateLoaderInterface::class);
        $loader->expects(self::once())->method('load')->with('magento_sku')->willReturn($definition);
        $source = $this->source([], $skuMappings, $loader);
        $actual = $source->getMappings();
        self::assertSame([7], array_keys($actual));
        if ($valid) {
            self::assertSame($mapping, $actual[7]);
        } else {
            self::assertSame($mapping, array_diff_key($actual[7], ['publication_error' => true]));
            self::assertStringContainsString('unique Ergonode Global Text', $actual[7]['publication_error']);
        }
    }

    /** @return array<string, array{AttributeState|null, bool}> */
    public static function skuDefinitions(): array
    {
        return [
            'keeps the saved SKU mapping when ordinary mappings exclude it' => [
                new AttributeState('magento_sku', 'text', 'global', parameters: ['unique' => true]),
                true,
            ],
            'missing remote attribute' => [null, false],
            'non-unique text' => [new AttributeState('magento_sku', 'text', 'global'), false],
            'localized text' => [
                new AttributeState('magento_sku', 'text', 'local', parameters: ['unique' => true]),
                false,
            ],
        ];
    }

    /** @param array<int, string> $stores @param ProductAttributeSourceValidatorInterface[] $validators */
    private function source(
        array $stores,
        ?SkuIdentityMappingProviderInterface $skuMappings = null,
        ?AttributeStateLoaderInterface $loader = null,
        array $validators = []
    ): ProductAttributePublicationSource {
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn($stores);

        return new ProductAttributePublicationSource(
            $this->createStub(CompleteMappingProviderInterface::class),
            $languages,
            new ProductDesiredStateFactory(),
            new ProductAttributeValueResolverPool(new ProductValueNormalizer()),
            new ProductAttributeSourceValidatorPool($validators),
            $skuMappings ?? $this->createStub(SkuIdentityMappingProviderInterface::class),
            $loader ?? $this->createStub(AttributeStateLoaderInterface::class)
        );
    }

    private function product(string $sku, int|string|null $value): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getData')->willReturn($value);

        return $product;
    }

    /** @param array<string, int|string|null> $values */
    private function productWithData(string $sku, array $values): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getData')->willReturnCallback(
            static fn (string $code): int|string|null => $values[$code] ?? null
        );

        return $product;
    }
}
