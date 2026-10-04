<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\ResourceModel;

use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryPaths;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class NativeGalleryPathsTest extends TestCase
{
    public function testVisibleGalleryIsScopedToProductAndUsesStoreDisabledOverride(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $joins = [];
        $select->expects(self::exactly(2))->method('joinLeft')->willReturnCallback(
            static function ($name, $on) use (&$joins, $select): Select { $joins[] = $on; return $select; }
        );
        $filters = [];
        $select->expects(self::exactly(5))->method('where')->willReturnCallback(
            static function ($condition, $value) use (&$filters, $select): Select {
                $filters[$condition] = $value; return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchCol')->with($select)->willReturn(['/a.jpg', '/a.jpg', '/c.jpg']);
        self::assertSame(['/a.jpg', '/c.jpg'], $this->reader($connection, 8)->get(23, 2));
        self::assertSame(['p.entity_id = ?' => 23, 'g.attribute_id = ?' => 8,
            'g.media_type = ?' => 'image', 'g.disabled = ?' => 0,
            'COALESCE(s.disabled, d.disabled, 0) = ?' => 0], $filters);
        self::assertStringContainsString('d.entity_id = p.entity_id AND d.store_id = 0', $joins[0]);
        self::assertStringContainsString('s.entity_id = p.entity_id AND s.store_id = 2', $joins[1]);
    }

    public function testMissingGalleryAttributeCannotBeMistakenForAnEmptyGallery(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('fetchCol');
        $this->expectException(LocalizedException::class);
        $this->reader($connection, 0)->get(23, 0);
    }

    private function reader(AdapterInterface $connection, int $id): NativeGalleryPaths
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn($id);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new NativeGalleryPaths($resource, $eav);
    }
}
