<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;

class ProductAttributePlacementPolicy implements ProductAttributePlacementPolicyInterface
{
    private const array MANAGED_ATTRIBUTE_CODES = [
        'price',
        'status',
        'visibility',
    ];

    private const array EXCLUDED_ATTRIBUTE_CODES = [
        'category_ids',
        'cost',
        'custom_design',
        'custom_design_from',
        'custom_design_to',
        'custom_layout',
        'custom_layout_update_file',
        'gallery',
        'gift_message_available',
        'image',
        'media_gallery',
        'msrp',
        'msrp_display_actual_price_type',
        'options_container',
        'page_layout',
        'price_type',
        'price_view',
        'quantity_and_stock_status',
        'shipment_type',
        'sku_type',
        'small_image',
        'special_from_date',
        'special_price',
        'special_to_date',
        'swatch_image',
        'tax_class_id',
        'thumbnail',
        'tier_price',
        'weight_type',
    ];

    public function isExcluded(string $attributeCode): bool
    {
        return in_array(strtolower(trim($attributeCode)), self::EXCLUDED_ATTRIBUTE_CODES, true);
    }

    public function isProtected(string $attributeCode): bool
    {
        $attributeCode = strtolower(trim($attributeCode));
        if ($attributeCode === '') {
            return false;
        }

        return $attributeCode === 'sku'
            || $attributeCode === 'url_key'
            || in_array($attributeCode, self::MANAGED_ATTRIBUTE_CODES, true)
            || $this->isExcluded($attributeCode);
    }
}
