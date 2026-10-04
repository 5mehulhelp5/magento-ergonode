<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Integration;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Gallery\GallerySynchronizer;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryWriter;
use Ergonode\ProductMedia\Model\ResourceModel\RoleWriter;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Framework\App\ResourceConnection;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Magento\Eav\Model\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[DbIsolation(true)]
class GalleryRolesIntegrationTest extends TestCase
{
    public function testSharedGalleryValueAndRoleReplacement(): void
    {
        $om = Bootstrap::getObjectManager();
        $resource = $om->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $products = $om->get(ProductResource::class);
        $writer = new NativeGalleryWriter($resource, $om->get(Config::class), $om->get(GalleryWriteLocks::class), $om->get(\Ergonode\ProductMedia\Api\GalleryConfigurationInterface::class));
        $roles = new RoleWriter($products, $om->get(Action::class));
        $sync = new GallerySynchronizer(
            $writer,
            $this->createStub(GalleryRulesInterface::class),
            $roles,
            $this->createStub(ImageRolesInterface::class)
        );
        $ids = [];
        $suffix = bin2hex(random_bytes(6));
        foreach ([1, 2] as $number) {
            $product = $om->create(Product::class);
            $product->setTypeId('simple')->setAttributeSetId(4)->setSku('media-roles-' . $suffix . '-' . $number)
                ->setName('Media roles test')->setPrice(10)->setStatus(1)->setVisibility(4);
            $products->save($product);
            $ids[] = (int)$product->getId();
        }
        $first = 'catalog/product/roles-' . $suffix . '-a.jpg';
        $second = 'catalog/product/roles-' . $suffix . '-b.jpg';
        $desired = [['path' => $first, 'position' => 1], ['path' => $second, 'position' => 2]];
        foreach ($ids as $id) {
            $sync->synchronize($id, $desired, []);
            self::assertSame('/' . substr($first, strlen('catalog/product/')), $products
                ->getAttributeRawValue($id, 'image', 0));
        }
        $gallery = $resource->getTableName('catalog_product_entity_media_gallery');
        $nativePath = '/' . substr($first, strlen('catalog/product/'));
        self::assertSame(1, (int)$connection->fetchOne($connection->select()->from($gallery, ['COUNT(*)'])
            ->where('value = ?', $nativePath)));
        $sync->synchronize($ids[0], [['path' => $second, 'position' => 1]], [$first, $second]);
        self::assertSame('/' . substr($second, strlen('catalog/product/')), $products
            ->getAttributeRawValue($ids[0], 'image', 0));
        self::assertSame($nativePath, $products->getAttributeRawValue($ids[1], 'image', 0));
        $sync->synchronize($ids[0], [], [$second]);
        foreach (['image', 'small_image', 'thumbnail'] as $code) {
            self::assertSame('no_selection', $products->getAttributeRawValue($ids[0], $code, 0));
        }
    }
}
