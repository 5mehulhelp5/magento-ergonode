<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class NativeGalleryPaths
{
    public function __construct(private readonly ResourceConnection $resource, private readonly Config $eav)
    {
    }

    /** @return list<string> Visible native paths, including retained unmanaged images. */
    public function get(int $productId, int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $attributeId = (int)$this->eav->getAttribute(Product::ENTITY, ProductInterface::MEDIA_GALLERY)->getAttributeId();
        if ($attributeId < 1) {
            throw new LocalizedException(__('Magento media_gallery attribute does not exist.'));
        }
        $table = $this->resource->getTableName('catalog_product_entity_media_gallery_value');
        $select = $connection->select()->from(
            ['g' => $this->resource->getTableName('catalog_product_entity_media_gallery')], ['value']
        )->joinInner(
            ['p' => $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity')],
            'p.value_id = g.value_id', []
        )->joinLeft(['d' => $table], 'd.value_id = g.value_id AND d.entity_id = p.entity_id AND d.store_id = 0', [])
            ->joinLeft(['s' => $table], 's.value_id = g.value_id AND s.entity_id = p.entity_id AND s.store_id = '
                . $storeId, [])
            ->where('p.entity_id = ?', $productId)->where('g.attribute_id = ?', $attributeId)
            ->where('g.media_type = ?', 'image')->where('g.disabled = ?', 0)
            ->where('COALESCE(s.disabled, d.disabled, 0) = ?', 0);
        return array_values(array_unique(array_map('strval', $connection->fetchCol($select))));
    }
}
