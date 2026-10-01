<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourceResultFactoryInterface;
use Ergonode\ProductPublisher\Model\Source\ProductRelationSourcePool;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductRelationSourcePoolTest extends TestCase
{
    public function testReturnsEmptyRelationsWhenTypeHasNoAdapter(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $relations = $this->createStub(ProductRelationStateInterface::class);
        $result = $this->createStub(ProductRelationSourceResultInterface::class);
        $stateFactory = $this->createMock(ProductDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::once())->method('createRelations')->willReturn($relations);
        $resultFactory = $this->createMock(ProductRelationSourceResultFactoryInterface::class);
        $resultFactory->expects(self::once())->method('create')->with($relations)->willReturn($result);

        self::assertSame(
            $result,
            (new ProductRelationSourcePool($stateFactory, $resultFactory))->extract($product, [])
        );
    }

    public function testDelegatesToRegisteredTypeAdapter(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn('configurable');
        $result = $this->createStub(ProductRelationSourceResultInterface::class);
        $source = $this->createMock(ProductRelationSourceInterface::class);
        $source->method('getMagentoTypeId')->willReturn('configurable');
        $source->expects(self::once())->method('extract')->with($product, ['color' => 'colour'])->willReturn($result);

        self::assertSame($result, (new ProductRelationSourcePool(
            $this->createStub(ProductDesiredStateFactoryInterface::class),
            $this->createStub(ProductRelationSourceResultFactoryInterface::class),
            [$source]
        ))->extract($product, ['color' => 'colour']));
    }
}
