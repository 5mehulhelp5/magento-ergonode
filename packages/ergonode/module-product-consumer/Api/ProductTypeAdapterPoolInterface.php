<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface ProductTypeAdapterPoolInterface
{
    /**
     * Resolve an enabled Magento product-type adapter without silently changing an existing type.
     *
     * @param string $ergonodeType
     * @param string|null $existingMagentoType
     * @param string|null $requestedMagentoType
     * @return ProductTypeAdapterInterface
     * @throws LocalizedException
     */
    public function resolve(
        string $ergonodeType,
        ?string $existingMagentoType = null,
        ?string $requestedMagentoType = null
    ): ProductTypeAdapterInterface;
}
