<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Type;

use Ergonode\ProductConsumer\Model\Type\NoRelationTypeAdapter;
use Ergonode\ProductConsumer\Model\Type\ProductTypeAdapterPool;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductTypeAdapterPoolTest extends TestCase
{
    public function testResolvesDefaultAndPreservesCompatibleExistingType(): void
    {
        $simple = new NoRelationTypeAdapter('simple', 'simple', true);
        $virtual = new NoRelationTypeAdapter('virtual', 'simple');
        $pool = new ProductTypeAdapterPool([$simple, $virtual]);

        self::assertSame($simple, $pool->resolve('simple'));
        self::assertSame($virtual, $pool->resolve('simple', 'virtual'));
        self::assertSame($virtual, $pool->resolve('simple', null, 'virtual'));
    }

    public function testRejectsIncompatibleExplicitType(): void
    {
        $pool = new ProductTypeAdapterPool([
            new NoRelationTypeAdapter('simple', 'simple', true),
            new NoRelationTypeAdapter('grouped', 'grouping', true),
        ]);

        $this->expectException(LocalizedException::class);
        $pool->resolve('simple', null, 'grouped');
    }

    public function testRejectsAmbiguousDefaults(): void
    {
        $pool = new ProductTypeAdapterPool([
            new NoRelationTypeAdapter('simple', 'simple', true),
            new NoRelationTypeAdapter('virtual', 'simple', true),
        ]);

        $this->expectException(LocalizedException::class);
        $pool->resolve('simple');
    }
}
