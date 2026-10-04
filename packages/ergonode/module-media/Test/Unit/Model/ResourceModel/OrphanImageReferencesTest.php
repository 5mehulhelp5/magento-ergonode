<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\ResourceModel\OrphanImageReferences;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrphanImageReferencesTest extends TestCase
{
    #[DataProvider('references')]
    public function testEveryRemainingUsePreventsPhysicalFileDeletion(array $results, bool $used): void
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'joinInner', 'where', 'limit'] as $method) { $select->method($method)->willReturnSelf(); }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::exactly(count($results)))->method('fetchOne')->willReturnOnConsecutiveCalls(...$results);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(9);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $roles = $this->createStub(ImageRolesInterface::class);
        $roles->method('getOptions')->willReturn(['image' => 'Image']);
        self::assertSame($used, (new OrphanImageReferences($resource, $eav, $roles))->isUsed('catalog/product/b.jpg'));
    }

    public static function references(): array
    {
        return ['gallery link' => [[42], true], 'image attribute' => [[false, 7], true],
            'pending gallery' => [[false, false, 42], true], 'file attribute usage' => [[false, false, false, 42], true],
            'no remaining reference' => [[false, false, false, false], false]];
    }
    public function testPartialStateCleanupOnlyDeletesUnlinkedImageRowsForTheExactPath(): void
    {
        $linked = $this->createStub(Select::class);
        $linked->method('from')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($linked);
        $deleted = [];
        $connection->expects(self::exactly(2))->method('delete')->willReturnCallback(
            static function ($table, $where) use (&$deleted): int { $deleted[$table] = $where; return 1; }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        (new OrphanImageReferences($resource, $this->createStub(Config::class),
            $this->createStub(ImageRolesInterface::class)))->forgetUnusedFile('catalog/product/b.jpg');
        self::assertSame([
            'BINARY value = BINARY ?' => '/b.jpg', 'media_type = ?' => 'image',
            'value_id NOT IN (?)' => $linked,
        ], $deleted['catalog_product_entity_media_gallery']);
        self::assertSame(['local_path = ?' => 'catalog/product/b.jpg'], $deleted['ergonode_media_materialization']);
    }

}
