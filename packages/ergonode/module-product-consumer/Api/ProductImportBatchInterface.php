<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

interface ProductImportBatchInterface
{
    /**
     * Import mapped attribute values into at most 50 existing, bound Magento products.
     * @param int[] $productIds
     * @return list<array{product_id: int, code: string, status: string, message: string}>
     */
    public function import(array $productIds): array;
}
