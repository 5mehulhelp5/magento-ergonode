<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Unit\Contract;

use Ergonode\ProductCategoryConsumer\Model\GraphQl\ProductCategoryQueries;
use PHPUnit\Framework\TestCase;

class ProductCategoryQueryContractTest extends TestCase
{
    public function testCategoryQueryUsesCodesAndBoundedPagination(): void
    {
        self::assertStringContainsString('product(sku: $sku)', ProductCategoryQueries::CATEGORIES);
        self::assertStringContainsString(
            'categoryList(first: $first, after: $after)',
            ProductCategoryQueries::CATEGORIES
        );
        self::assertStringContainsString('edges { node { code } }', ProductCategoryQueries::CATEGORIES);
    }
}
