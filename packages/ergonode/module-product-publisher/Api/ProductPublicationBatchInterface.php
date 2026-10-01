<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

interface ProductPublicationBatchInterface
{
    /**
     * Publish at most 50 explicitly selected products without a background job or automatic replay.
     * @param int[] $productIds
     * @return list<array{product_id: int, code: string, status: string, message: string, publication_at: string}>
     */
    public function publish(array $productIds): array;
}
