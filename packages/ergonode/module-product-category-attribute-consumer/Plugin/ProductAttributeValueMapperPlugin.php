<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Plugin;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributeValueMapperPlugin
{
    public function __construct(
        private readonly CategoryReferenceAttributeConfigInterface $config,
        private readonly ProductAttributeMappingProviderInterface $mappingProvider
    ) {
    }

    /**
     * @param array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>} $result
     * @param \Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute[] $attributes
     * @param string[]|null $attributeCodes
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     */
    public function afterMap(
        ProductAttributeValueMapper $subject,
        array $result,
        array $attributes,
        ?array $attributeCodes = null
    ): array {
        unset($subject, $attributes);
        if ($attributeCodes !== null && !in_array($this->config->getAttributeCode(), $attributeCodes, true)) {
            return $result;
        }

        return $this->validateRequiredValue($result);
    }

    /**
     * @param array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>} $result
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     */
    public function afterMapSpecial(ProductAttributeValueMapper $subject, array $result): array
    {
        unset($subject);

        return $this->validateRequiredValue($result);
    }

    /**
     * @param array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>} $result
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     */
    private function validateRequiredValue(array $result): array
    {
        $attributeCode = $this->config->getAttributeCode();
        if ($attributeCode === '' || !$this->config->isRequired()) {
            return $result;
        }
        $mapped = false;
        foreach ($this->mappingProvider->getMappings() as $mapping) {
            if ($this->config->isConfigured((string)($mapping['magento_attribute_code'] ?? ''))) {
                $mapped = true;
                break;
            }
        }
        if (!$mapped) {
            throw new LocalizedException(__(
                'Required Magento product attribute "%1" has no complete Ergonode mapping.',
                $attributeCode
            ));
        }
        if (!isset($result['values'][$attributeCode][0])) {
            throw new LocalizedException(__(
                'Required Magento product attribute "%1" has no value for the admin store.',
                $attributeCode
            ));
        }

        return $result;
    }
}
