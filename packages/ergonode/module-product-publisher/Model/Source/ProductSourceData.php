<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Magento\Catalog\Model\Product;

final readonly class ProductSourceData
{
    /**
     * @param array<string, Product> $products
     * @param array<int, array<string, Product>> $storeProducts
     * @param array<int, array<string, mixed>> $attributeMappings
     * @param array<int, string> $templateCodes
     * @param array<int, string[]> $attributeCodesBySet Ergonode attribute codes keyed by Magento attribute-set ID.
     */
    public function __construct(
        private array $products,
        private array $storeProducts,
        private array $attributeMappings,
        private array $templateCodes,
        private array $attributeCodesBySet
    ) {
    }

    /** @return array<string, Product> */
    public function getProducts(): array
    {
        return $this->products;
    }

    /** @return array<int, array<string, Product>> */
    public function getStoreProducts(): array
    {
        return $this->storeProducts;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAttributeMappings(): array
    {
        return $this->attributeMappings;
    }

    /** @return array<int, string> */
    public function getTemplateCodes(): array
    {
        return $this->templateCodes;
    }

    /** @return string[] Ergonode attribute codes. */
    public function getAttributeCodesForSet(int $attributeSetId): array
    {
        return $this->attributeCodesBySet[$attributeSetId] ?? [];
    }
}
