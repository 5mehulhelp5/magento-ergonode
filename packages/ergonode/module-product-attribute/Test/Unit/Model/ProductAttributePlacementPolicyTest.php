<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model;

use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use PHPUnit\Framework\TestCase;

class ProductAttributePlacementPolicyTest extends TestCase
{
    public function testProtectsSystemManagedAttributes(): void
    {
        $policy = new ProductAttributePlacementPolicy();

        self::assertTrue($policy->isProtected('sku'));
        self::assertTrue($policy->isProtected('special_price'));
        self::assertFalse($policy->isProtected('color'));
        self::assertFalse($policy->isProtected(''));
    }

    public function testExcludedAttributesAreNormalized(): void
    {
        $policy = new ProductAttributePlacementPolicy();

        self::assertTrue($policy->isExcluded(' MEDIA_GALLERY '));
        self::assertFalse($policy->isExcluded('manufacturer'));
    }
}
