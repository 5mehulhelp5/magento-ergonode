<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

interface ProductAttributeSourceValidatorInterface
{
    /**
     * Validate one mapped Magento product attribute before publication.
     *
     * @param Product $product
     * @param array<string, mixed> $mapping
     * @param array<string, float|string|string[]> $translations
     * @param array<string, mixed> $sourceValues Original store values, keyed by published language.
     * @return void
     * @throws LocalizedException
     */
    public function validate(Product $product, array $mapping, array $translations, array $sourceValues): void;
}
