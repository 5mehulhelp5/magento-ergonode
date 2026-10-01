<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Model\Sync\ProductDependencyLayerPlanner;
use Ergonode\ProductPublisher\Model\Data\ProductRelationState;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use PHPUnit\Framework\TestCase;

class ProductDependencyLayerPlannerTest extends TestCase
{
    public function testNumericSkusRemainStringsAndPreserveLeadingZeros(): void
    {
        $products = [
            '1234' => new ProductState('1234', 'simple', 'default'),
            '01234' => new ProductState('01234', 'simple', 'default'),
            '0' => new ProductState('0', 'simple', 'default'),
        ];

        $layers = (new ProductDependencyLayerPlanner())->plan($products);

        self::assertCount(1, $layers);
        $skus = $layers[0];
        sort($skus, SORT_STRING);
        self::assertSame(['0', '01234', '1234'], $skus);
    }

    public function testNumericGroupedChildIsPublishedBeforeItsNumericParent(): void
    {
        $products = [
            '200' => new ProductState(
                '200',
                'grouping',
                'default',
                relations: new ProductRelationState(groupedChildren: ['100' => 1])
            ),
            '100' => new ProductState('100', 'simple', 'default'),
        ];

        self::assertSame(
            [['100'], ['200']],
            (new ProductDependencyLayerPlanner())->plan($products)
        );
    }

    public function testNumericCycleSharesOneLayerBeforeItsDependant(): void
    {
        $products = [
            '100' => new ProductState(
                '100',
                'variable',
                'default',
                relations: new ProductRelationState(variantSkus: ['200'])
            ),
            '200' => new ProductState(
                '200',
                'variable',
                'default',
                relations: new ProductRelationState(variantSkus: ['100'])
            ),
            '300' => new ProductState(
                '300',
                'grouping',
                'default',
                relations: new ProductRelationState(groupedChildren: ['100' => 1])
            ),
        ];

        self::assertSame(
            [['100', '200'], ['300']],
            (new ProductDependencyLayerPlanner())->plan($products)
        );
    }

    public function testBuildsDependencyFirstLayersAndKeepsIndependentProductsTogether(): void
    {
        $products = [
            'PARENT' => new ProductState(
                'PARENT',
                'grouping',
                'default',
                relations: new ProductRelationState(groupedChildren: ['CHILD' => 1])
            ),
            'CHILD' => new ProductState('CHILD', 'simple', 'default'),
            'INDEPENDENT' => new ProductState('INDEPENDENT', 'simple', 'default'),
        ];

        self::assertSame(
            [['CHILD', 'INDEPENDENT'], ['PARENT']],
            (new ProductDependencyLayerPlanner())->plan($products)
        );
    }

    public function testMutuallyDependentProductsShareOneLayerBeforeTheirDependants(): void
    {
        $products = [
            'A' => new ProductState(
                'A',
                'variable',
                'default',
                relations: new ProductRelationState(variantSkus: ['B'])
            ),
            'B' => new ProductState(
                'B',
                'variable',
                'default',
                relations: new ProductRelationState(variantSkus: ['A'])
            ),
            'PARENT' => new ProductState(
                'PARENT',
                'grouping',
                'default',
                relations: new ProductRelationState(groupedChildren: ['A' => 1])
            ),
        ];

        self::assertSame(
            [['A', 'B'], ['PARENT']],
            (new ProductDependencyLayerPlanner())->plan($products)
        );
    }
}
