<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\GraphQl;

use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use PHPUnit\Framework\TestCase;

class ProductMutationFactoryTest extends TestCase
{
    public function testAssignedSkuCreateOmitsSkuAndSelectsGeneratedIdentity(): void
    {
        $operation = (new ProductMutationFactory())->createWithAssignedSku(
            new ProductState('MAGENTO-1', 'simple', 'template')
        );

        self::assertSame('productCreateSimple', $operation->getField());
        self::assertSame([
            'templateCode' => 'template',
        ], $operation->getVariables()['input']->getValue());
        self::assertSame(['product.sku'], $operation->getResponseFields());
        self::assertSame('MAGENTO-1', $operation->getMetadata()['entity_sku']);
    }

    public function testBuildsEveryTypedValueMutationWithVariablesAndCorrelation(): void
    {
        $values = [
            'date' => '2026-08-08',
            'file' => ['/files/spec.pdf'],
            'gallery' => ['/images/one.jpg'],
            'image' => '/images/hero.jpg',
            'multi_select' => ['red', 'blue'],
            'numeric' => 12.5,
            'price' => 99.0,
            'product_relation' => ['RELATED-1'],
            'select' => 'red',
            'text' => '',
            'textarea' => '<p>Description</p>',
            'unit' => 10,
        ];
        $expectedSuffixes = [
            'Date', 'File', 'Gallery', 'Image', 'MultiSelect', 'Numeric',
            'Price', 'ProductRelation', 'Select', 'Text', 'Textarea', 'Unit',
        ];
        $factory = new ProductMutationFactory();
        $fields = [];
        foreach ($values as $type => $translation) {
            $operation = $factory->setValue(
                'SKU-1',
                new ProductAttributeValue('attribute_' . $type, $type, ['pl_PL' => $translation]),
                'pl_PL'
            );
            $fields[] = $operation->getField();
            self::assertSame('SKU-1', $operation->getMetadata()['entity_sku']);
            self::assertSame('pl_PL', $operation->getMetadata()['language']);
            self::assertSame('SKU-1', $operation->getVariables()['input']->getValue()['sku']);
        }

        self::assertSame(array_map(
            static fn (string $suffix): string => 'productAddAttributeValueTranslations' . $suffix,
            $expectedSuffixes
        ), $fields);
    }

    public function testProductRelationCarriesSchemaEnum(): void
    {
        $operation = (new ProductMutationFactory())->setValue(
            'SKU-1',
            new ProductAttributeValue('related', 'product_relation', ['pl_PL' => ['SKU-2']], 'New'),
            'pl_PL'
        );

        self::assertSame('New', $operation->getVariables()['input']->getValue()['twoWayRelation']);
    }
}
