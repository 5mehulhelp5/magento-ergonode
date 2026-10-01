<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Model\Data\ProductAttributeValuesResultDto;
use Magento\Catalog\Model\Product;

interface ProductAttributePublicationSourceInterface
{
    /**
     * Attribute mappings contributed by the optional outbound attribute extension.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMappings(): array;

    /**
     * Build only the mapped values assigned to this product's attribute set.
     *
     * @param Product $product
     * @param array<int, array<string, Product>> $storeProducts
     * @param array<int, array<string, mixed>> $mappings
     * @return ProductAttributeValuesResultDto
     */
    public function getValues(Product $product, array $storeProducts, array $mappings): ProductAttributeValuesResultDto;
}
