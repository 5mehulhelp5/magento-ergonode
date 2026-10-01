<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface ValueAdapterInterface
{
    /**
     * @param string $attributeCode Magento attribute code.
     * @return bool
     */
    public function supports(string $attributeCode): bool;

    /**
     * @param string $direction Import or publish.
     * @return bool
     */
    public function isAvailable(string $direction): bool;
}
