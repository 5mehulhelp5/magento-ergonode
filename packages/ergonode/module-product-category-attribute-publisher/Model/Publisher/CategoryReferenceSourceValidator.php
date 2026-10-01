<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Model\Publisher;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductPublisher\Api\ProductAttributeSourceValidatorInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

class CategoryReferenceSourceValidator implements ProductAttributeSourceValidatorInterface
{
    public function __construct(
        private readonly CategoryReferenceAttributeConfigInterface $config
    ) {
    }

    public function validate(Product $product, array $mapping, array $translations, array $sourceValues): void
    {
        if (!$this->config->isConfigured((string)($mapping['magento_attribute_code'] ?? ''))) {
            return;
        }
        $sku = (string)$product->getSku();
        $assignedIds = array_map('intval', $product->getCategoryIds());
        foreach ($translations as $languageCode => $categoryCode) {
            $categoryId = filter_var(
                $sourceValues[$languageCode] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($categoryId === false || !in_array($categoryId, $assignedIds, true)) {
                throw new LocalizedException(__(
                    'Default category "%1" is not assigned to Magento product "%2".',
                    is_scalar($categoryCode) ? (string)$categoryCode : '',
                    $sku
                ));
            }
        }
    }
}
