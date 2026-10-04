<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;

class OrphanImageReferences
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $eav,
        private readonly ImageRolesInterface $roles
    ) {
    }

    public function isUsed(string $path): bool
    {
        $connection = $this->resource->getConnection();
        $nativePath = '/' . substr($path, strlen('catalog/product/'));
        $gallery = $connection->select()->from(
            ['g' => $this->resource->getTableName('catalog_product_entity_media_gallery')], ['value_id']
        )->joinInner(['p' => $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity')],
            'p.value_id = g.value_id', [])->where('BINARY g.value = BINARY ?', $nativePath)->limit(1);
        if ($connection->fetchOne($gallery) !== false) {
            return true;
        }
        $ids = [];
        foreach (array_keys($this->roles->getOptions()) as $code) {
            $id = (int)$this->eav->getAttribute(Product::ENTITY, $code)->getAttributeId();
            if ($id > 0) { $ids[] = $id; }
        }
        if ($ids !== [] && $connection->fetchOne($connection->select()->from(
            $this->resource->getTableName('catalog_product_entity_varchar'), ['value_id']
        )->where('attribute_id IN (?)', $ids)->where('BINARY value = BINARY ?', $nativePath)->limit(1)) !== false) {
            return true;
        }
        foreach (['ergonode_media_product_usage', 'ergonode_media_file_usage'] as $table) {
            if ($connection->fetchOne($connection->select()->from(
                $this->resource->getTableName($table), ['product_id']
            )->where('attached_path = ?', $path)->where('desired = ?', 1)->limit(1)) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Called only after the reference check and successful physical cleanup. */
    public function forgetUnusedFile(string $path): void
    {
        $connection = $this->resource->getConnection();
        // A previous partial write may have left a native row without any product association.
        $linked = $connection->select()->from(
            $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity'), ['value_id']
        );
        $connection->delete($this->resource->getTableName('catalog_product_entity_media_gallery'), [
            'BINARY value = BINARY ?' => '/' . substr($path, strlen('catalog/product/')),
            'media_type = ?' => 'image',
            'value_id NOT IN (?)' => $linked,
        ]);
        $connection->delete($this->resource->getTableName('ergonode_media_materialization'), [
            'local_path = ?' => $path,
        ]);
    }
}
