<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Data;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductRelationState;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductStateTest extends TestCase
{
    public function testMappedModeKeepsMagentoAndErgonodeSkusSeparate(): void
    {
        $state = new ProductState(
            'MAG-1',
            ProductStateInterface::TYPE_SIMPLE,
            'template',
            magentoProductId: 23,
            ergonodeSku: 'NAV-1',
            identityMode: ProductIdentityInterface::MODE_MAPPED
        );

        self::assertSame('MAG-1', $state->getSku());
        self::assertSame('NAV-1', $state->getErgonodeSku());
        self::assertSame(ProductIdentityInterface::MODE_MAPPED, $state->getIdentityMode());
    }

    public function testNormalizesRelationsAndPreservesEmptyListValues(): void
    {
        $value = new ProductAttributeValue('colors', 'multi_select', ['pl_PL' => []]);
        $state = new ProductState(
            ' SKU-1 ',
            ProductStateInterface::TYPE_VARIABLE,
            'template',
            ['pl_PL' => 'active'],
            [$value],
            new ProductRelationState(['color'], ['SKU-2', 'SKU-2'])
        );

        self::assertSame('SKU-1', $state->getSku());
        self::assertSame([], $state->getValues()[0]->getTranslations()['pl_PL']);
        self::assertSame(['SKU-2'], $state->getRelations()->getVariantSkus());
    }

    public function testRejectsRelationshipForWrongProductType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductState(
            'SKU-1',
            ProductStateInterface::TYPE_SIMPLE,
            'template',
            relations: new ProductRelationState(variantSkus: ['SKU-2'])
        );
    }

    public function testRejectsInvalidTypedValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductAttributeValue('price', 'price', ['pl_PL' => '12.00']);
    }

    public function testCanonicalizesFloatAndSetLikeValues(): void
    {
        $numeric = new ProductAttributeValue('weight', 'unit', ['pl_PL' => 10]);
        $relations = new ProductAttributeValue(
            'related',
            'product_relation',
            ['pl_PL' => ['SKU-2', 'SKU-1', 'SKU-2']]
        );

        self::assertSame(10.0, $numeric->getTranslations()['pl_PL']);
        self::assertSame(['SKU-1', 'SKU-2'], $relations->getTranslations()['pl_PL']);
    }

    public function testCarriesCollectionCompletenessAndRejectsUnknownKeys(): void
    {
        $state = new ProductState('SKU-1', 'simple', 'default', authoritativeCollections: [
            ProductCollectionCompletenessInterface::COLLECTION_VALUES . ':name' => true,
        ]);

        self::assertTrue($state->isCollectionAuthoritative(
            ProductCollectionCompletenessInterface::COLLECTION_VALUES,
            'name'
        ));
        self::assertFalse($state->isCollectionAuthoritative(
            ProductCollectionCompletenessInterface::COLLECTION_VARIANTS
        ));

        $this->expectException(InvalidArgumentException::class);
        new ProductState('SKU-2', 'simple', 'default', authoritativeCollections: ['unknown' => true]);
    }

    public function testRejectsTranslationAndClearForSameLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductAttributeValue('name', 'text', ['pl_PL' => 'Name'], clearedLanguageCodes: ['pl_PL']);
    }
}
