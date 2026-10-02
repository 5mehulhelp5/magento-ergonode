<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Ergonode\ProductMedia\Model\Port\GalleryWriterInterface;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;

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
        private readonly GalleryWriteLocks $locks
    ) {
    }

    /**
     * Synchronize paths managed by Ergonode with the native gallery tables.
     *
     * @param list<array{path:string,position:int}> $desired
     * @param string[] $managed
     */
    public function synchronize(int $productId, array $desired, array $managed): void
    {
        $connection = $this->resource->getConnection();
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
            }
        }
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
