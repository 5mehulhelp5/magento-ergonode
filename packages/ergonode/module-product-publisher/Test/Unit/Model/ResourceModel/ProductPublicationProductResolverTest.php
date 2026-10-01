<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\ResourceModel;

use Ergonode\ProductPublisher\Model\ResourceModel\ProductPublicationProductResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class ProductPublicationProductResolverTest extends TestCase
{
    public function testResolvesNormalizedRequestedSkusWithoutDroppingZeroSku(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('catalog_product_entity', ['entity_id', 'sku'])
            ->willReturnSelf();
        $select->expects(self::once())
            ->method('where')
            ->with('sku IN (?)', ['SKU-A', '0'])
            ->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn([
            ['entity_id' => '10', 'sku' => 'SKU-A'],
            ['entity_id' => '20', 'sku' => '0'],
        ]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('catalog_product_entity')->willReturn('catalog_product_entity');

        $result = (new ProductPublicationProductResolver($resource))->getProductSkusBySkus([
            ' SKU-A ',
            'SKU-A',
            '',
            '0',
        ]);

        self::assertSame([10 => 'SKU-A', 20 => '0'], $result);
    }
}
