<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Ergonode\Product\Api\ProductStateSnapshotInterface;
use Magento\Framework\App\ResourceConnection;

class GalleryStateSnapshot implements ProductStateSnapshotInterface
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function get(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $db = $this->resource->getConnection();
        $select = $db->select()->from(['p' => $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity')], ['entity_id'])
            ->joinInner(['g' => $this->resource->getTableName('catalog_product_entity_media_gallery')], 'g.value_id = p.value_id',
                ['attribute_id', 'value', 'media_type', 'global_disabled' => 'disabled'])
            ->joinLeft(['v' => $this->resource->getTableName('catalog_product_entity_media_gallery_value')],
                'v.value_id = p.value_id AND v.entity_id = p.entity_id', ['store_id', 'label', 'position', 'disabled'])
            ->where('p.entity_id IN (?)', $productIds)
            ->order(['p.entity_id', 'g.attribute_id', 'g.value', 'g.media_type', 'v.store_id']);
        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $id = (int)$row['entity_id'];
            unset($row['entity_id']);
            $result[$id][] = $row;
        }
        return $result;
    }
}
