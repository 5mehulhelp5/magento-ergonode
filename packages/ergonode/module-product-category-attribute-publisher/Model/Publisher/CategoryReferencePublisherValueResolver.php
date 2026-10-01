<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributePublisher\Model\Publisher;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductPublisher\Api\ProductAttributeValueResolverInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryReferencePublisherValueResolver implements ProductAttributeValueResolverInterface
{
    public function __construct(
        private readonly CategoryReferenceAttributeConfigInterface $config,
        private readonly CategoryMappingProviderInterface $categoryMappingProvider
    ) {
    }

    public function supports(array $mapping): bool
    {
        return $this->config->isConfigured((string)($mapping['magento_attribute_code'] ?? ''));
    }

    public function resolve(mixed $value, array $mapping, string $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((string)($mapping['ergonode_type'] ?? '') !== 'text') {
            throw new LocalizedException(__('Ergonode category reference for %1 must use the Text type.', $context));
        }
        $categoryId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false) {
            throw new LocalizedException(__('Magento value for %1 must be a category ID.', $context));
        }
        $categoryCodes = $this->categoryMappingProvider->getCategoryCodesByMagentoIds([(int)$categoryId]);
        $categoryCode = trim((string)($categoryCodes[(int)$categoryId] ?? ''));
        if ($categoryCode === '') {
            throw new LocalizedException(__(
                'Magento category ID "%1" has no Ergonode mapping for %2.',
                (int)$categoryId,
                $context
            ));
        }

        return $categoryCode;
    }
}
