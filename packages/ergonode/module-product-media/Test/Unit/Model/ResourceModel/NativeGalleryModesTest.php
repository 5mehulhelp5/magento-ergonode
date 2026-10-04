<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\ResourceModel;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Ergonode\ProductMedia\Model\Gallery\GallerySynchronizer;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Ergonode\ProductMedia\Model\Gallery\ObsoleteImageRoles;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryWriter;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryPaths;
use Ergonode\ProductMedia\Model\ResourceModel\RoleWriter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NativeGalleryModesTest extends TestCase
{
    #[DataProvider('modes')]
    public function testSharedImageOnAnotherProductSurvivesEveryMode(UnmanagedImagesMode $mode): void
    {
        $images = [1 => ['value_id' => 1, 'value' => '/a.jpg'], 2 => ['value_id' => 2, 'value' => '/b.jpg'],
            3 => ['value_id' => 3, 'value' => '/c.jpg'], 4 => ['value_id' => 4, 'value' => '/video']];
        $links = [23 => [1, 2, 3, 4], 42 => [2]];
        $metadata = [23 => [1 => [0 => ['disabled' => 0]], 2 => [0 => ['disabled' => 0, 'label' => 'Manual B', 'position' => 7],
            2 => ['disabled' => 0]], 3 => [0 => ['disabled' => 0]], 4 => [0 => ['disabled' => 0]]],
            42 => [2 => [0 => ['disabled' => 0, 'label' => 'Other product']]]];
        $roleValues = [23 => [0 => ['image' => '/b.jpg', 'manual_image' => '/b.jpg'], 2 => ['manual_image' => '/b.jpg']],
            42 => [0 => ['image' => '/b.jpg', 'manual_image' => '/b.jpg']]];
        $otherLinks = $links[42]; $otherMetadata = $metadata[42]; $otherRoles = $roleValues[42];
        $connection = $this->createStub(AdapterInterface::class);
        $queries = [];
        $connection->method('select')->willReturnCallback(function () use (&$queries): Select {
            $select = $this->createStub(Select::class);
            $id = spl_object_id($select);
            $queries[$id] = [];
            $select->method('from')->willReturnSelf();
            $select->method('joinInner')->willReturnSelf();
            $select->method('order')->willReturnSelf();
            $select->method('limit')->willReturnSelf();
            $select->method('forUpdate')->willReturnSelf();
            $select->method('where')->willReturnCallback(static function ($key, $value) use (&$queries, $id, $select): Select {
                $queries[$id][$key] = $value;
                return $select;
            });
            return $select;
        });
        $connection->method('fetchCol')->willReturnCallback(static function (Select $select) use (&$queries, &$images): array {
            $query = $queries[spl_object_id($select)];
            self::assertSame(9, $query['attribute_id = ?']);
            $path = $query['BINARY value = BINARY ?'];
            return array_keys(array_filter($images, static fn ($row) => $row['value'] === $path));
        });
        $connection->method('fetchOne')->willReturn(42);
        $connection->method('fetchAll')->willReturnCallback(static function (Select $select) use (&$queries, &$images, &$links): array {
            $query = $queries[spl_object_id($select)];
            self::assertSame(23, $query['p.entity_id = ?']);
            self::assertSame(9, $query['g.attribute_id = ?']);
            self::assertSame('image', $query['g.media_type = ?']);
            return array_values(array_filter($images, static fn ($row) => $row['value_id'] !== 4
                && in_array($row['value_id'], $links[23], true)));
        });
        $connection->method('insert')->willReturnCallback(static function (): never {
            self::fail('An existing shared gallery value must not be recreated.');
        });
        $connection->method('insertOnDuplicate')->willReturnCallback(
            static function (string $table, array $rows, array $columns) use (&$links, &$metadata): int {
                self::assertContains($table, ['catalog_product_entity_media_gallery_value_to_entity', 'catalog_product_entity_media_gallery_value']);
                foreach ($rows as $row) {
                    self::assertSame(23, $row['entity_id']);
                    $valueId = $row['value_id'];
                    if ($table === 'catalog_product_entity_media_gallery_value_to_entity') {
                        $links[23] = array_values(array_unique([...$links[23], $valueId]));
                    } else {
                        $store = $row['store_id'];
                        $previous = $metadata[23][$valueId][$store] ?? null;
                        $metadata[23][$valueId][$store] = $previous === null ? $row : array_replace(
                            $previous, array_intersect_key($row, array_flip($columns))
                        );
                    }
                }
                return 1;
            }
        );
        $connection->method('update')->willReturnCallback(
            static function (string $table, array $data, array $where) use (&$metadata): int {
                self::assertSame('catalog_product_entity_media_gallery_value', $table);
                self::assertSame(23, $where['entity_id = ?']);
                foreach ($metadata[23][$where['value_id = ?']] ?? [] as $store => $row) {
                    $metadata[23][$where['value_id = ?']][$store] = array_replace($row, $data);
                }
                return 1;
            }
        );
        $connection->method('delete')->willReturnCallback(
            static function (string $table, array $where) use (&$links, &$metadata): int {
                self::assertSame(23, $where['entity_id = ?']);
                self::assertContains($table, ['catalog_product_entity_media_gallery_value_to_entity', 'catalog_product_entity_media_gallery_value']);
                $id = $where['value_id = ?'];
                if ($table === 'catalog_product_entity_media_gallery_value_to_entity') {
                    $links[23] = array_values(array_diff($links[23], [$id]));
                } else {
                    unset($metadata[23][$id]);
                }
                return 1;
            }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(9);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('getUnmanagedImagesMode')->willReturn($mode);
        $native = new NativeGalleryWriter($resource, $eav, new GalleryWriteLocks($locks), $configuration);
        $products = $this->createStub(Product::class);
        $products->method('getAttributeRawValue')->willReturnCallback(
            static function ($id, array $codes, $store) use (&$roleValues): array {
                return array_intersect_key($roleValues[$id][$store] ?? [], array_flip($codes));
            }
        );
        $action = $this->createStub(Action::class);
        $action->method('updateAttributes')->willReturnCallback(
            static function ($ids, $values, $store) use (&$roleValues): void {
                self::assertSame([23], $ids);
                $roleValues[23][$store] = array_replace($roleValues[23][$store] ?? [], $values);
            }
        );
        $roles = new RoleWriter($products, $action);
        $available = $this->createStub(ImageRolesInterface::class);
        $available->method('getOptions')->willReturn(['image' => 'Image', 'small_image' => 'Small',
            'thumbnail' => 'Thumbnail', 'manual_image' => 'Manual']);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([$store]);
        $reader = $this->createStub(NativeGalleryPaths::class);
        $reader->method('get')->willReturnCallback(static function ($id, $store) use (&$links, &$images, &$metadata): array {
            $paths = [];
            foreach ($links[$id] as $valueId) {
                if ($valueId !== 4 && ($metadata[$id][$valueId][$store]['disabled'] ?? $metadata[$id][$valueId][0]['disabled'] ?? 0) === 0) {
                    $paths[] = $images[$valueId]['value'];
                }
            }
            return $paths;
        });
        $sync = new GallerySynchronizer($native, $this->createStub(GalleryRulesInterface::class), $roles, $available,
            new ObsoleteImageRoles($products, $available, $roles, $stores, $reader));
        $desired = [['path' => 'catalog/product/a.jpg', 'position' => 1], ['path' => 'catalog/product/c.jpg', 'position' => 2]];
        $sync->synchronize(23, $desired, []);
        self::assertSame($otherLinks, $links[42]);
        self::assertSame($otherMetadata, $metadata[42]);
        self::assertSame($otherRoles, $roleValues[42]);
        self::assertSame('/b.jpg', $images[2]['value']);
        self::assertContains(4, $links[23]); // Video associations are outside this photo policy.
        self::assertSame(0, $metadata[23][4][0]['disabled']);
        self::assertSame('/a.jpg', $roleValues[23][0]['image']);
        if ($mode === UnmanagedImagesMode::Keep) {
            self::assertContains(2, $links[23]);
            self::assertSame(0, $metadata[23][2][0]['disabled']);
            self::assertSame('/b.jpg', $roleValues[23][0]['manual_image']);
        } elseif ($mode === UnmanagedImagesMode::Hide) {
            self::assertContains(2, $links[23]);
            self::assertSame(1, $metadata[23][2][0]['disabled']);
            self::assertSame(1, $metadata[23][2][2]['disabled']);
            self::assertSame('Manual B', $metadata[23][2][0]['label']);
            self::assertSame(7, $metadata[23][2][0]['position']);
        } else {
            self::assertNotContains(2, $links[23]);
            self::assertArrayNotHasKey(2, $metadata[23]);
        }
        if ($mode !== UnmanagedImagesMode::Keep) {
            self::assertSame('no_selection', $roleValues[23][0]['manual_image']);
            self::assertSame('no_selection', $roleValues[23][2]['manual_image']);
        }
        // A deliberately started later import makes B visible again without touching product 42.
        $sync->synchronize(23, [['path' => 'catalog/product/b.jpg', 'position' => 1]], ['catalog/product/a.jpg', 'catalog/product/c.jpg']);
        self::assertContains(2, $links[23]);
        self::assertSame(0, $metadata[23][2][0]['disabled']);
        if (isset($metadata[23][2][2])) { self::assertSame(0, $metadata[23][2][2]['disabled']); }
        self::assertSame($otherLinks, $links[42]);
        self::assertSame($otherMetadata, $metadata[42]);
        self::assertSame($otherRoles, $roleValues[42]);
    }

    public static function modes(): array
    {
        return ['keep' => [UnmanagedImagesMode::Keep], 'hide' => [UnmanagedImagesMode::Hide], 'remove' => [UnmanagedImagesMode::Remove]];
    }
}
