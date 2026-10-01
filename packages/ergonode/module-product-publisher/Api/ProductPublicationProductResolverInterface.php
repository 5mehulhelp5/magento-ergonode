<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

interface ProductPublicationProductResolverInterface
{
    /**
     * Return current SKUs keyed by Magento product ID. Missing IDs are omitted.
     *
     * @param int[] $productIds
     * @return array<int, string>
     */
    public function getCurrentSkus(array $productIds): array;

    /**
     * Return Magento SKUs keyed by product ID. Unknown SKUs are omitted.
     *
     * @param string[] $skus
     * @return array<int, string>
     */
    public function getProductSkusBySkus(array $skus): array;
}
