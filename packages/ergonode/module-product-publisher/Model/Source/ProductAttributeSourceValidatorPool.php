<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductAttributeSourceValidatorInterface;
use Magento\Catalog\Model\Product;

class ProductAttributeSourceValidatorPool
{
    /** @param ProductAttributeSourceValidatorInterface[] $validators */
    public function __construct(private readonly array $validators = [])
    {
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, float|string|string[]> $translations
     * @param array<string, mixed> $sourceValues Original store values, keyed by published language.
     */
    public function validate(Product $product, array $mapping, array $translations, array $sourceValues): void
    {
        foreach ($this->validators as $validator) {
            $validator->validate($product, $mapping, $translations, $sourceValues);
        }
    }
}
