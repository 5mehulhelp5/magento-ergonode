<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateConsumer\Model\Sync\AttributeSetResource;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Entity\Type;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class AttributeSetResourceTest extends TestCase
{
    public function testCachesProductEntityTypeId(): void
    {
        $select = $this->createSelect();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('select')->willReturn($select);
        $connection->expects($this->once())->method('fetchOne')->with($select)->willReturn('4');

        $resource = new AttributeSetResource(
            $this->createResourceConnection($connection),
            $this->createStub(ProductResource::class)
        );

        self::assertSame(4, $resource->getProductEntityTypeId());
        self::assertSame(4, $resource->getProductEntityTypeId());
    }

    public function testReturnsDefaultProductAttributeSetId(): void
    {
        $entityType = $this->createStub(Type::class);
        $entityType->method('getDefaultAttributeSetId')->willReturn('9');

        $productResource = $this->createStub(ProductResource::class);
        $productResource->method('getEntityType')->willReturn($entityType);

        $resource = new AttributeSetResource(
            $this->createStub(ResourceConnection::class),
            $productResource
        );

        self::assertSame(9, $resource->getDefaultProductAttributeSetId());
    }

    public function testMapsProductAttributeSetRows(): void
    {
        $entityTypeSelect = $this->createSelect();
        $attributeSetSelect = $this->createSelect();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($entityTypeSelect, $attributeSetSelect);
        $connection->expects($this->once())->method('fetchOne')->with($entityTypeSelect)->willReturn('4');
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($attributeSetSelect)
            ->willReturn([
                ['attribute_set_id' => '12', 'attribute_set_name' => 'Default'],
                ['attribute_set_id' => '18', 'attribute_set_name' => 'Premium'],
            ]);

        $resource = new AttributeSetResource(
            $this->createResourceConnection($connection),
            $this->createStub(ProductResource::class)
        );

        self::assertSame(
            [
                ['id' => 12, 'name' => 'Default'],
                ['id' => 18, 'name' => 'Premium'],
            ],
            $resource->getProductAttributeSets()
        );
    }

    private function createSelect(): Select
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'limit', 'order'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        return $select;
    }

    private function createResourceConnection(AdapterInterface $connection): ResourceConnection
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(static fn (string $table): string => $table);

        return $resourceConnection;
    }
}
