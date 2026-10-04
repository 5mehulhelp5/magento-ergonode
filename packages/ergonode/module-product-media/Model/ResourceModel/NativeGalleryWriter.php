<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Ergonode\ProductMedia\Model\Port\GalleryWriterInterface;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class NativeGalleryWriter implements GalleryWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $eav,
        private readonly GalleryWriteLocks $locks,
        private readonly GalleryConfigurationInterface $configuration
    ) {
    }

    /**
     * Synchronize paths managed by Ergonode with the native gallery tables.
     *
     * @param list<array{path:string,position:int}> $desired
     * @param string[] $managed
     * @return list<string> Removed or hidden paths.
     */
    public function synchronize(int $productId, array $desired, array $managed): array
    {
        $connection = $this->resource->getConnection();
        $mode = $this->configuration->getUnmanagedImagesMode();
        $retired = [];
        // Acquire retained path locks in a stable order across products.
        usort($desired, fn(array $a, array $b): int => $this->path($a['path']) <=> $this->path($b['path']));
        $wanted = [];
        foreach ($desired as $item) {
            $path = $this->path($item['path']);
            $wanted[$path] = (int)$item['position'];
            $valueId = $this->valueId($path);
            $connection->insertOnDuplicate(
                $this->table('catalog_product_entity_media_gallery_value_to_entity'),
                [['value_id' => $valueId, 'entity_id' => $productId]],
                []
            );
            // An image returning to the source gallery is visible in every existing store override.
            $connection->update($this->table('catalog_product_entity_media_gallery_value'), ['disabled' => 0], [
                'value_id = ?' => $valueId, 'entity_id = ?' => $productId,
            ]);
            $connection->insertOnDuplicate(
                $this->table('catalog_product_entity_media_gallery_value'),
                [[
                    'value_id' => $valueId,
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'label' => null,
                    'position' => (int)$item['position'],
                    'disabled' => 0,
                ]],
                ['label', 'position', 'disabled']
            );
        }
        foreach (array_unique($managed) as $path) {
            $path = $this->path($path);
            if (isset($wanted[$path])) {
                continue;
            }
            $retired[$path] = $path;
            foreach ($this->valueIds($path) as $id) {
                $condition = ['value_id = ?' => $id, 'entity_id = ?' => $productId];
                $connection->delete(
                    $this->table('catalog_product_entity_media_gallery_value_to_entity'),
                    $condition
                );
                $connection->delete(
                    $this->table('catalog_product_entity_media_gallery_value'),
                    $condition
                );
                $this->deleteUnusedValue($id);
            }
        }
        if ($mode !== UnmanagedImagesMode::Keep) {
            foreach ($this->linkedImages($productId) as $row) {
                $path = $this->path('catalog/product/' . ltrim((string)$row['value'], '/'));
                if (isset($wanted[$path])) {
                    continue;
                }
                $id = (int)$row['value_id'];
                $condition = ['value_id = ?' => $id, 'entity_id = ?' => $productId];
                $retired[$path] = $path;
                if ($mode === UnmanagedImagesMode::Remove) {
                    $connection->delete($this->table('catalog_product_entity_media_gallery_value_to_entity'), $condition);
                    $connection->delete($this->table('catalog_product_entity_media_gallery_value'), $condition);
                    $this->deleteUnusedValue($id);
                } else {
                    $connection->update($this->table('catalog_product_entity_media_gallery_value'), ['disabled' => 1], $condition);
                    // Some native associations have no default metadata row yet.
                    $connection->insertOnDuplicate($this->table('catalog_product_entity_media_gallery_value'), [[
                        'value_id' => $id, 'entity_id' => $productId, 'store_id' => 0,
                        'label' => null, 'position' => 0, 'disabled' => 1,
                    ]], ['disabled']);
                }
            }
        }
        return array_values($retired);
    }

    /** The gallery row is locked by valueIds/linkedImages until the caller's transaction completes. */
    private function deleteUnusedValue(int $valueId): void
    {
        $connection = $this->resource->getConnection();
        $inUse = $connection->fetchOne($connection->select()->from(
            $this->table('catalog_product_entity_media_gallery_value_to_entity'), ['entity_id']
        )->where('value_id = ?', $valueId)->limit(1)->forUpdate(true));
        if ($inUse === false) {
            $connection->delete($this->table('catalog_product_entity_media_gallery'), ['value_id = ?' => $valueId]);
        }
    }

    /** @return list<array{value_id:int|string,value:string}> */
    private function linkedImages(int $productId): array
    {
        $select = $this->resource->getConnection()->select()->from(
            ['g' => $this->table('catalog_product_entity_media_gallery')], ['value_id', 'value']
        )->joinInner(
            ['p' => $this->table('catalog_product_entity_media_gallery_value_to_entity')],
            'p.value_id = g.value_id', []
        )->where('p.entity_id = ?', $productId)->where('g.attribute_id = ?', $this->attributeId())
            ->where('g.media_type = ?', 'image')->order('g.value_id ASC')->forUpdate(true);
        return $this->resource->getConnection()->fetchAll($select);
    }

    private function valueId(string $path): int
    {
        return $this->locks->forPath($path, function () use ($path): int {
            $ids = $this->valueIds($path);
            if ($ids !== []) {
                return $ids[0];
            }
            $connection = $this->resource->getConnection();
            $connection->insert(
                $this->table('catalog_product_entity_media_gallery'),
                [
                    'attribute_id' => $this->attributeId(),
                    'value' => '/' . substr($path, strlen('catalog/product/')),
                    'media_type' => 'image',
                    'disabled' => 0,
                ]
            );
            return $this->valueIds($path)[0];
        });
    }
    /**
     * Find native gallery values for a local path.
     *
     * @return int[]
     */
    private function valueIds(string $path): array
    {
        $select = $this->resource->getConnection()->select()
            ->from($this->table('catalog_product_entity_media_gallery'), ['value_id'])
            ->where('attribute_id = ?', $this->attributeId())
            ->where('BINARY value = BINARY ?', '/' . substr($path, strlen('catalog/product/')))
            ->order('value_id ASC')->forUpdate(true);

        return array_map('intval', $this->resource->getConnection()->fetchCol($select));
    }
    private function attributeId(): int
    {
        $id = (int)$this->eav->getAttribute(Product::ENTITY, ProductInterface::MEDIA_GALLERY)->getAttributeId();
        if ($id < 1) {
            throw new LocalizedException(__('Magento media_gallery attribute does not exist.'));
        }
        return $id;
    }
    private function path(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($path, 'catalog/product/') || str_contains($path, '..')) {
            throw new LocalizedException(__('Invalid Magento gallery path.'));
        }
        return $path;
    }
    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
