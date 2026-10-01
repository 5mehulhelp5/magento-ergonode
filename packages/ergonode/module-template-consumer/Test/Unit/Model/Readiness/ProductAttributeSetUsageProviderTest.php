<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Readiness;

use Ergonode\TemplateConsumer\Model\Readiness\ProductAttributeSetUsageProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class ProductAttributeSetUsageProviderTest extends TestCase
{
    public function testCountsUsedAttributeSetsForSelectedProductsWithOneQuery(): void
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'group', 'order'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->expects($this->exactly(2))
            ->method('where')
            ->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn([
                ['attribute_set_id' => '4', 'product_count' => '3'],
                ['attribute_set_id' => '7', 'product_count' => '1'],
            ]);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->expects($this->once())
            ->method('getTableName')
            ->with('catalog_product_entity')
            ->willReturn('catalog_product_entity');

        $usage = (new ProductAttributeSetUsageProvider($resourceConnection))->getUsage(['SKU-1', 'SKU-2']);

        self::assertSame([4 => 3, 7 => 1], $usage);
    }
}
