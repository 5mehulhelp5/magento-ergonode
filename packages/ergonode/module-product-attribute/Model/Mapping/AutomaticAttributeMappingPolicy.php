<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;

/**
 * Limits automatic mapping only. Explicit mappings remain governed by the manual mapping policy.
 */
class AutomaticAttributeMappingPolicy
{
    private const array EXCLUDED_ATTRIBUTE_CODES = [
        'gallery',
        'is_in_stock',
        'media_gallery',
        'price',
        'qty',
        'quantity_and_stock_status',
        'special_price',
        'status',
        'stock',
        'stock_status',
        'url_key',
        'visibility',
    ];

    public function __construct(private readonly ProductAttributePolicy $attributePolicy)
    {
    }

    public function isAllowed(string $attributeCode): bool
    {
        $attributeCode = strtolower(trim($attributeCode));

        return $attributeCode !== ''
            && !in_array($attributeCode, self::EXCLUDED_ATTRIBUTE_CODES, true)
            && !$this->attributePolicy->isIdentityAttribute($attributeCode);
    }
}
