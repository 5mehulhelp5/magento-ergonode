<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Magento\Framework\Indexer\IndexerRegistry;

class ProductIndexInvalidator
{
    private const array INDEXER_IDS = [
        'catalog_product_attribute',
        'catalog_product_price',
        'catalog_category_product',
        'catalog_product_category',
        'inventory',
    ];

    public function __construct(private readonly IndexerRegistry $indexerRegistry)
    {
    }

    public function invalidate(): void
    {
        foreach (self::INDEXER_IDS as $indexerId) {
            $this->indexerRegistry->get($indexerId)->invalidate();
        }
    }
}
