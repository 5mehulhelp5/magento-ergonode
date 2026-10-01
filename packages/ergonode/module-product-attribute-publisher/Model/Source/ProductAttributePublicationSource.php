<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Source;

use Ergonode\AttributePublisher\Api\AttributeStateLoaderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Api\SkuIdentityMappingProviderInterface;
use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValuesResultDto;
use Ergonode\ProductPublisher\Model\Exception\MissingOptionMappingException;
use Ergonode\ProductPublisher\Model\Source\ProductAttributeSourceValidatorPool;
use Ergonode\ProductPublisher\Model\Source\ProductAttributeValueResolverPool;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributePublicationSource implements ProductAttributePublicationSourceInterface
{
    public function __construct(
        private readonly CompleteMappingProviderInterface $mappingProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ProductDesiredStateFactoryInterface $stateFactory,
        private readonly ProductAttributeValueResolverPool $valueResolverPool,
        private readonly ProductAttributeSourceValidatorPool $attributeValidators,
        private readonly SkuIdentityMappingProviderInterface $skuMappingProvider,
        private readonly AttributeStateLoaderInterface $attributeStateLoader
    ) {
    }

    public function getMappings(): array
    {
        $mappings = $this->mappingProvider->getMappings('publish');
        foreach ($this->mappingProvider->getMappings() as $id => $mapping) {
            if (!isset($mappings[$id])) {
                $mapping['publication_error'] = (string)__(
                    'Publication adapter "%1" is unavailable.',
                    (string)($mapping['value_adapter'] ?? '')
                );
                $mappings[$id] = $mapping;
            }
        }
        $skuMapping = $this->skuMappingProvider->getMapping();
        if ($skuMapping !== null) {
            $attribute = $this->attributeStateLoader->load($skuMapping['ergonode_attribute_code']);
            if ($attribute === null
                || $attribute->getType() !== 'text'
                || strtolower($attribute->getScope()) !== 'global'
                || empty($attribute->getParameters()['unique'])
            ) {
                $skuMapping['publication_error'] = (string)__(
                    'The attribute mapped to Magento SKU must be a unique Ergonode Global Text attribute.'
                );
            }
            $mappings[$skuMapping['mapping_id']] = $skuMapping;
        }

        return $mappings;
    }

    public function getValues(Product $product, array $storeProducts, array $mappings): ProductAttributeValuesResultDto
    {
        $values = [];
        $warnings = [];
        foreach ($mappings as $mapping) {
            if (isset($mapping['publication_error'])) {
                $warnings[] = (string)__(
                    'Product "%1": omitted attribute "%2" in all languages: %3',
                    (string)$product->getSku(),
                    (string)$mapping['magento_attribute_code'],
                    (string)$mapping['publication_error']
                );
                continue;
            }
            try {
                [$translations, $cleared, $sourceValues] = $this->translations($product, $storeProducts, $mapping);
                $this->attributeValidators->validate($product, $mapping, $translations, $sourceValues);
                $values[] = $this->stateFactory->createValue(
                    $mapping['ergonode_attribute_code'],
                    $mapping['ergonode_type'],
                    $translations,
                    null,
                    $cleared
                );
            } catch (MissingOptionMappingException $exception) {
                $warnings[] = (string)__(
                    'Product "%1": omitted attribute "%2" in all languages; '
                        . 'Magento option ID(s) without Ergonode mapping: %3.',
                    (string)$product->getSku(),
                    (string)$mapping['magento_attribute_code'],
                    implode(', ', $exception->getOptionIds())
                );
            } catch (LocalizedException $exception) {
                $warnings[] = (string)__(
                    'Product "%1": omitted attribute "%2" in all languages: %3',
                    (string)$product->getSku(),
                    (string)$mapping['magento_attribute_code'],
                    $exception->getMessage()
                );
            }
        }
        return new ProductAttributeValuesResultDto($values, $warnings);
    }

    /**
     * @param array<int, array<string, Product>> $storeProducts
     * @param array<string, mixed> $mapping
     * @return array{array<string, float|string|string[]>, string[], array<string, mixed>}
     */
    private function translations(Product $product, array $storeProducts, array $mapping): array
    {
        $sku = (string)$product->getSku();
        $magentoCode = (string)$mapping['magento_attribute_code'];
        $context = 'product "' . $sku . '" attribute "' . $magentoCode . '"';
        $result = [];
        $cleared = [];
        $projectedLanguages = [];
        $sourceValues = [];
        $missingOptions = [];
        foreach ($this->languageMappingProvider->getLanguageStoreMap() as $storeId => $languageCode) {
            if ($storeId === 0 || isset($projectedLanguages[$languageCode])) {
                continue;
            }
            $projectedLanguages[$languageCode] = true;
            $storeProduct = $storeProducts[$storeId][$sku] ?? null;
            $sourceValue = $storeProduct?->getData($magentoCode);
            try {
                $value = $this->valueResolverPool->resolve($sourceValue, $mapping, $context);
            } catch (MissingOptionMappingException $exception) {
                foreach ($exception->getOptionIds() as $optionId) {
                    $missingOptions[$optionId] = true;
                }
                continue;
            }
            if ($value === null) {
                $cleared[] = $languageCode;
            } else {
                $result[$languageCode] = $value;
                $sourceValues[$languageCode] = $sourceValue;
            }
        }
        if ($missingOptions !== []) {
            $ids = array_map('strval', array_keys($missingOptions));
            sort($ids);
            throw new MissingOptionMappingException($ids, $context);
        }
        ksort($result);
        sort($cleared);

        return [$result, $cleared, $sourceValues];
    }
}
