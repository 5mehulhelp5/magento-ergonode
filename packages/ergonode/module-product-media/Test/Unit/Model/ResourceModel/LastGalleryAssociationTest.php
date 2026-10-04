<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\ResourceModel;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryWriter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LastGalleryAssociationTest extends TestCase
{
    #[DataProvider('references')]
    public function testGlobalGalleryRecordIsDeletedOnlyAfterItsLastProductAssociation(bool $otherProduct): void
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'where', 'order', 'limit', 'forUpdate'] as $method) { $select->method($method)->willReturnSelf(); }
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([2]);
        $connection->method('fetchOne')->willReturn($otherProduct ? 42 : false);
        $deletes = [];
        $connection->expects(self::exactly($otherProduct ? 2 : 3))->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$deletes): int { $deletes[] = [$table, $where]; return 1; }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(9);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('getUnmanagedImagesMode')->willReturn(UnmanagedImagesMode::Keep);
        $native = new NativeGalleryWriter($resource, $eav,
            new GalleryWriteLocks($this->createStub(LockManagerInterface::class)), $configuration);
        self::assertSame(['catalog/product/b.jpg'], $native->synchronize(23, [], ['catalog/product/b.jpg']));
        self::assertSame(['catalog_product_entity_media_gallery_value_to_entity', ['value_id = ?' => 2, 'entity_id = ?' => 23]], $deletes[0]);
        self::assertSame(['catalog_product_entity_media_gallery_value', ['value_id = ?' => 2, 'entity_id = ?' => 23]], $deletes[1]);
        if (!$otherProduct) {
            self::assertSame(['catalog_product_entity_media_gallery', ['value_id = ?' => 2]], $deletes[2]);
        }
    }

    public static function references(): array
    {
        return ['last association' => [false], 'second product still attached' => [true]];
    }
}
