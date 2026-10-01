<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Plugin;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;

class ProductAttributePlacementPolicyPlugin
{
    public function __construct(private readonly CategoryReferenceAttributeConfigInterface $config)
    {
    }

    public function afterIsProtected(
        ProductAttributePlacementPolicyInterface $subject,
        bool $result,
        string $attributeCode
    ): bool {
        unset($subject);

        return $result || $this->config->isConfigured($attributeCode);
    }
}
