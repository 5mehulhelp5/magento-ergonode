<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface ProductAttributePlacementPolicyInterface
{
    /**
     * Check whether an attribute is excluded from Ergonode product mapping.
     *
     * @param string $attributeCode
     * @return bool
     */
    public function isExcluded(string $attributeCode): bool;

    /**
     * Check whether template synchronization must preserve the attribute placement.
     *
     * @param string $attributeCode
     * @return bool
     */
    public function isProtected(string $attributeCode): bool;
}
