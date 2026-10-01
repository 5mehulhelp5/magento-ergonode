<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Model\Consumer;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueResolverInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeResolutionScopeInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryReferenceConsumerValueResolver implements
    ProductAttributeValueResolverInterface,
    ProductAttributeResolutionScopeInterface
{
    public function __construct(
        private readonly CategoryReferenceAttributeConfigInterface $config,
        private readonly CategoryReferenceCategoryIdResolver $categoryIdResolver
    ) {
    }

    public function supports(array $mapping): bool
    {
        return $this->config->isConfigured((string)($mapping['magento_attribute_code'] ?? ''));
    }

    public function resetResolutionScope(): void
    {
        $this->categoryIdResolver->resetResolutionScope();
    }

    public function resolve(
        float|int|string|array|null $value,
        array $mapping,
        string $languageCode,
        int $storeId
    ): ?int {
        if ($value === null || $value === '' || $value === []) {
            if ($storeId === 0 && $this->config->isRequired()) {
                throw new LocalizedException(__(
                    'Required Ergonode category reference is missing for attribute "%1" and language "%2".',
                    (string)($mapping['ergonode_attribute_code'] ?? ''),
                    $languageCode
                ));
            }

            return null;
        }
        if ((string)($mapping['ergonode_type'] ?? '') !== 'text') {
            throw new LocalizedException(__('Ergonode category reference must use the Text type.'));
        }
        if (!is_scalar($value)) {
            throw new LocalizedException(__('Ergonode category reference must contain one category code.'));
        }
        $categoryCode = trim((string)$value);
        if ($categoryCode === '') {
            if ($storeId === 0 && $this->config->isRequired()) {
                throw new LocalizedException(__(
                    'Required Ergonode category reference is missing for attribute "%1" and language "%2".',
                    (string)($mapping['ergonode_attribute_code'] ?? ''),
                    $languageCode
                ));
            }

            return null;
        }

        return $this->categoryIdResolver->resolve($categoryCode, $storeId);
    }
}
