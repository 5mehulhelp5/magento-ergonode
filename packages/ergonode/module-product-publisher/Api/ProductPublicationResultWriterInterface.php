<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

interface ProductPublicationResultWriterInterface
{
    /**
     * Replace the last publication result for each existing Magento product.
     *
     * @param list<array{product_id: int, status: string, message: string}> $items
     * @return string Time recorded in UTC, formatted as Y-m-d H:i:s.
     */
    public function save(array $items): string;
}
