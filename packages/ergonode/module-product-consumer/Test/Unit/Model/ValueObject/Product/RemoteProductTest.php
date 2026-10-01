<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\ValueObject\Product;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Test\Unit\Support\RemoteProductAttributeFixture;
use PHPUnit\Framework\TestCase;

class RemoteProductTest extends TestCase
{
    public function testContentHashIsStableAcrossRelationshipOrdering(): void
    {
        $left = new RemoteProduct(
            'SKU',
            'variable',
            'template',
            false,
            ['pl_PL' => 'active', 'en_GB' => 'draft'],
            [
                RemoteProductAttributeFixture::string('z', 'text', ['pl_PL' => 'Z']),
                RemoteProductAttributeFixture::string('a', 'text', ['pl_PL' => 'A']),
            ],
            ['size', 'color'],
            ['V-2', 'V-1']
        );
        $right = new RemoteProduct(
            'SKU',
            'variable',
            'template',
            false,
            ['en_GB' => 'draft', 'pl_PL' => 'active'],
            [
                RemoteProductAttributeFixture::string('a', 'text', ['pl_PL' => 'A']),
                RemoteProductAttributeFixture::string('z', 'text', ['pl_PL' => 'Z']),
            ],
            ['color', 'size'],
            ['V-1', 'V-2']
        );

        self::assertSame($left->contentHash(), $right->contentHash());
    }

    public function testPreservesIntegerNumericValuesInCanonicalPayload(): void
    {
        $product = new RemoteProduct(
            'SKU',
            'simple',
            'template',
            false,
            [],
            [RemoteProductAttributeFixture::number('quantity', 'numeric', ['pl_PL' => 12])]
        );

        self::assertSame(12, $product->normalized()['attributes'][0]['values']['pl_PL']);
    }
}
