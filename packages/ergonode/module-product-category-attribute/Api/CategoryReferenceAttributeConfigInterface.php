<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Api;

interface CategoryReferenceAttributeConfigInterface
{
    /**
     * Return the Magento product attribute code whose value references a category.
     *
     * @return string Empty when category-reference mapping is disabled.
     */
    public function getAttributeCode(): string;

    /**
     * Check whether the Magento product attribute uses category-reference mapping.
     *
     * @param string $attributeCode
     * @return bool
     */
    public function isConfigured(string $attributeCode): bool;

    /**
     * Check whether the configured Magento product attribute is required.
     *
     * @return bool
     */
    public function isRequired(): bool;
}
