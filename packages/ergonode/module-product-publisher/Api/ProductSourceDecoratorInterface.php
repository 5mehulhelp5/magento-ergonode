<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Magento\Catalog\Model\Product;

interface ProductSourceDecoratorInterface
{
    /**
     * Decorate normalized product states with optional domain-owned source data.
     *
     * @param ProductStateInterface[] $states
     * @param array<string, Product> $products Magento products keyed by SKU.
     * @return ProductStateInterface[]
     */
    public function decorate(array $states, array $products): array;
}
