<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Source;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductCategoryPublisher\Model\Source\MagentoProductCategorySource;
use Ergonode\ProductCategoryPublisher\Model\Source\ProductCategorySourceDecorator;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductCategorySourceDecoratorTest extends TestCase
{
    public function testDecoratesBaseStateWithMappedCategoryCodes(): void
    {
        $product = $this->createStub(Product::class);
        $categorySource = $this->createMock(MagentoProductCategorySource::class);
        $categorySource->expects(self::once())
            ->method('load')
            ->with(['SKU-1' => $product])
            ->willReturn(['SKU-1' => ['codes' => ['chairs'], 'authoritative' => true]]);

        $result = (new ProductCategorySourceDecorator($categorySource))->decorate(
            [new ProductState('SKU-1', 'simple', 'default')],
            ['SKU-1' => $product]
        );

        $state = $result[0];
        self::assertInstanceOf(ProductCategoryStateInterface::class, $state);
        self::assertSame(['chairs'], $state->getCategoryCodes());
        self::assertTrue($state->isCategorySourceAuthoritative());
    }
}
