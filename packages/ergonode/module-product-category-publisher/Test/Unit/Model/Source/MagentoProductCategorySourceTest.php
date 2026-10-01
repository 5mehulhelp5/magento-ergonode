<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\Source;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductCategoryPublisher\Model\Source\MagentoProductCategorySource;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class MagentoProductCategorySourceTest extends TestCase
{
    public function testLoadsMappedCodesAndMarksProductsWithUnmappedAssignmentsAsIncomplete(): void
    {
        $firstProduct = $this->createStub(Product::class);
        $firstProduct->method('getId')->willReturn(10);
        $secondProduct = $this->createStub(Product::class);
        $secondProduct->method('getId')->willReturn(20);

        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'order'])
            ->getMock();
        $select->expects(self::once())
            ->method('from')
            ->with('catalog_category_product', ['product_id', 'category_id'])
            ->willReturnSelf();
        $select->expects(self::once())->method('where')->with('product_id IN (?)', [10, 20])->willReturnSelf();
        $select->expects(self::once())
            ->method('order')
            ->with(['product_id ASC', 'position ASC', 'category_id ASC'])
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([
            ['product_id' => '10', 'category_id' => '3'],
            ['product_id' => '10', 'category_id' => '4'],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $mappingProvider = $this->createMock(CategoryMappingProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getCategoryCodesByMagentoIds')
            ->with([3, 4])
            ->willReturn([3 => 'chairs']);

        $result = (new MagentoProductCategorySource($resource, $mappingProvider))->load([
            'SKU-1' => $firstProduct,
            'SKU-2' => $secondProduct,
        ]);

        self::assertSame(['codes' => ['chairs'], 'authoritative' => false], $result['SKU-1']);
        self::assertSame(['codes' => [], 'authoritative' => true], $result['SKU-2']);
    }
}
