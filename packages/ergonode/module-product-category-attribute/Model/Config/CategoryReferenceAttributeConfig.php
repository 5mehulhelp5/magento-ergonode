<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Model\Config;

use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CategoryReferenceAttributeConfig implements CategoryReferenceAttributeConfigInterface
{
    public const string XML_PATH_ATTRIBUTE = 'ergonode_products/attributes/default_category_attribute';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EavConfig $eavConfig
    ) {
    }

    public function getAttributeCode(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTE));
    }

    public function isConfigured(string $attributeCode): bool
    {
        $configuredCode = $this->getAttributeCode();

        return $configuredCode !== '' && $configuredCode === trim($attributeCode);
    }

    public function isRequired(): bool
    {
        $attributeCode = $this->getAttributeCode();
        if ($attributeCode === '') {
            return false;
        }

        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);

        return (int)$attribute->getAttributeId() > 0 && (bool)$attribute->getIsRequired();
    }
}
