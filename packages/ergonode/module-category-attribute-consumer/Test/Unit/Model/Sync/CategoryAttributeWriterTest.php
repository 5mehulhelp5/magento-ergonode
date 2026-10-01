<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CategoryAttributeWriterTest extends TestCase
{
    public function testSkipsInvalidCategoryIdWithoutDatabaseAccess(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $writer = new CategoryAttributeWriter(
            $resource,
            $this->createStub(CategoryResource::class),
            $this->createStub(EavConfig::class),
            $this->createStub(CategoryRepository::class),
            $this->createStub(CategoryAttributeValueMapper::class)
        );

        self::assertSame(0, $writer->writeMappedValues(0, []));
    }

    public function testDeletesValuesRemovedFromErgonodeForMappedStores(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->with($select)->willReturn([0 => 'Old', 2 => 'Old translation']);
        $connection->expects(self::once())->method('delete')->with(
            'catalog_category_entity_varchar',
            [
                'attribute_id = ?' => 55,
                'entity_id = ?' => 42,
                'store_id IN (?)' => [0, 2],
            ]
        )->willReturn(2);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $categoryResource = $this->createStub(CategoryResource::class);
        $categoryResource->method('getLinkField')->willReturn('entity_id');
        $categoryResource->method('getIdFieldName')->willReturn('entity_id');
        $attribute = $this->getMockBuilder(AbstractAttribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributeId', 'getBackendTable'])
            ->getMock();
        $attribute->method('getAttributeId')->willReturn(55);
        $attribute->method('getBackendTable')->willReturn('catalog_category_entity_varchar');
        $attribute->setData('is_global', 0);
        $eavConfig = $this->createStub(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);
        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())->method('_resetState');
        $mapper = $this->createStub(CategoryAttributeValueMapper::class);
        $mapper->method('mapForSynchronization')->willReturn([
            'values' => [],
            'clear' => ['category_subtitle' => [0, 2]],
        ]);

        $writer = new CategoryAttributeWriter(
            $resource,
            $categoryResource,
            $eavConfig,
            $repository,
            $mapper
        );

        self::assertSame(2, $writer->writeMappedValues(42, []));
    }
}
