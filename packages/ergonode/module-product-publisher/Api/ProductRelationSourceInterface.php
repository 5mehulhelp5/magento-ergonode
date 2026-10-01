<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Magento\Catalog\Model\Product;

interface ProductRelationSourceInterface
{
    /**
     * @return string
     */
    public function getMagentoTypeId(): string;

    /**
     * @param Product $product
     * @param array<string, string> $targetCodesByMagentoCode
     * @return ProductRelationSourceResultInterface
     */
    public function extract(Product $product, array $targetCodesByMagentoCode): ProductRelationSourceResultInterface;
}
