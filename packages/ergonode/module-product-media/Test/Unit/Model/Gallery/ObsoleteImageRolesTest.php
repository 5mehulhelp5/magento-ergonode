<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Gallery\ObsoleteImageRoles;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryPaths;
use Ergonode\ProductMedia\Model\ResourceModel\RoleWriter;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ObsoleteImageRolesTest extends TestCase
{
    public function testNextExplicitPassClearsAnAttributePointingOutsideTheAlreadyUpdatedGallery(): void
    {
        $values = [0 => ['image' => '/a.jpg', 'stale_image' => '/b.jpg', 'valid_image' => '/c.jpg'],
            2 => ['stale_image' => '/b.jpg', 'valid_image' => '/c.jpg']];
        $products = $this->createStub(Product::class);
        $products->method('getAttributeRawValue')->willReturnCallback(static function ($id, $codes, $store) use (&$values): array {
            self::assertSame(23, $id);
            return array_intersect_key($values[$store], array_flip($codes));
        });
        $action = $this->createMock(Action::class);
        $action->expects(self::exactly(2))->method('updateAttributes')->willReturnCallback(
            static function ($ids, array $updated, $store) use (&$values): void {
                self::assertSame([23], $ids);
                self::assertSame(['stale_image' => 'no_selection'], $updated);
                $values[$store] = array_replace($values[$store], $updated);
            }
        );
        $roles = $this->createStub(ImageRolesInterface::class);
        $roles->method('getOptions')->willReturn(['image' => 'Image', 'stale_image' => 'Old', 'valid_image' => 'Current']);
        $reader = $this->createStub(NativeGalleryPaths::class);
        $reader->method('get')->willReturn(['/a.jpg', '/c.jpg']);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([$store]);
        $cleanup = new ObsoleteImageRoles($products, $roles, new RoleWriter($products, $action), $stores, $reader);
        self::assertSame(['catalog/product/b.jpg'], $cleanup->clearMissing(23));
        self::assertSame('no_selection', $values[0]['stale_image']);
        self::assertSame('no_selection', $values[2]['stale_image']);
        self::assertSame('/c.jpg', $values[0]['valid_image']);
        self::assertSame('/a.jpg', $values[0]['image']);
        // A later explicit pass is a no-op; no queue or automatic retry is involved.
        self::assertSame([], $cleanup->clearMissing(23));
    }
}
