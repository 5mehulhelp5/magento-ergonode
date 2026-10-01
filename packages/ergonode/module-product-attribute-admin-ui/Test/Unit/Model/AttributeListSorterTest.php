<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Ergonode\ProductAttributeAdminUi\Model\AttributeListSorter;

class AttributeListSorterTest extends TestCase
{
    public function testSortsLabelsLikeDefaultFrontendSortAndKeepsEqualLabelsStable(): void
    {
        $attributes = [
            ['label' => 'Produkt 10', 'code' => 'product_10'],
            ['label' => ' alfa ', 'code' => 'first_alfa'],
            ['label' => 'Produkt 2', 'code' => 'product_2'],
            ['label' => 'Alfa', 'code' => 'second_alfa'],
            ['label' => 'Beta', 'code' => 'beta'],
        ];

        $sorter = new AttributeListSorter();

        self::assertSame(
            ['first_alfa', 'second_alfa', 'beta', 'product_2', 'product_10'],
            array_column($sorter->sortByLabel($attributes), 'code')
        );
    }
}
