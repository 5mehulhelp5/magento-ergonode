<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class ProductTypeDiscriminator
{
    public function __construct(
        private readonly ProductImportConfig $config,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function resolve(RemoteProduct $product): ?string
    {
        $attributeCode = $this->config->getTypeAttributeCode();
        foreach ($product->attributes as $attribute) {
            if ($attribute->code !== $attributeCode) {
                continue;
            }
            $values = $attribute->values->all();
            $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();
            $value = $adminLanguage !== null && array_key_exists($adminLanguage, $values)
                ? $values[$adminLanguage]
                : reset($values);
            if (is_array($value)) {
                $value = reset($value);
            }
            $value = strtolower(trim((string)$value));

            return $value !== '' ? str_replace('-', '_', $value) : null;
        }

        return null;
    }
}
