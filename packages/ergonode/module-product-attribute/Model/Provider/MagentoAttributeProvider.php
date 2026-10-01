<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Provider;

use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Throwable;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;

class MagentoAttributeProvider
{
    private const array EXCLUDED_NATIVE_ATTRIBUTE_LABEL_FRAGMENTS = [
        'special price',
    ];

    /**
     * @var array<string, array<int, array<string, bool|string>>>
     */
    private array $attributesCache = [];

    /**
     * @var array<string, array<string, array<string, bool|string>>>
     */
    private array $attributeMapCache = [];

    /**
     * @var array<string, Attribute>
     */
    private array $attributeResources = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly MappingVisibilityProviderInterface $visibilityProvider,
        private readonly MagentoAttributeTypeResolverInterface $typeResolver,
        private readonly ProductAttributePolicy $attributePolicy,
        private readonly MagentoAttributeMetadataContributorPool $metadataContributors
    ) {
    }

    /**
     * @return array<int, array<string, bool|string>>
     */
    public function getAttributes(bool $includeExcluded = false): array
    {
        $cacheKey = $includeExcluded ? 'all' : 'visible';
        if (isset($this->attributesCache[$cacheKey])) {
            return $this->attributesCache[$cacheKey];
        }

        $collection = $this->collectionFactory->create();
        $collection->addVisibleFilter();
        $collections = [$collection];
        $additionalCodes = $this->metadataContributors->getAdditionalAttributeCodes();
        if ($additionalCodes !== []) {
            $configuredCollection = $this->collectionFactory->create();
            $configuredCollection->addFieldToFilter('attribute_code', ['in' => $additionalCodes]);
            $collections[] = $configuredCollection;
        }
        $attributes = [];
        $codes = [];

        foreach ($collections as $attributeCollection) {
            foreach ($attributeCollection as $attribute) {
                if (!$attribute instanceof Attribute) {
                    continue;
                }

                $code = (string)$attribute->getAttributeCode();
                if ($code === '' || in_array($code, $codes, true)) {
                    continue;
                }

                $label = (string)($attribute->getDefaultFrontendLabel() ?: $code);
                if (!$includeExcluded && $this->isExcludedNativeAttribute($attribute, $code, $label)) {
                    continue;
                }

                $required = (bool)$attribute->getIsRequired()
                    || $this->attributePolicy->isMappingRequired($code);
                $codes[] = $code;
                $this->attributeResources[$code] = $attribute;
                $attributes[] = $this->metadataContributors->contribute(
                    [
                    'label' => $label,
                    'code' => $code,
                    'scope' => $this->resolveScope($attribute),
                    'type' => $this->typeResolver->fromStorage(
                        (string)$attribute->getFrontendInput(),
                        (string)$attribute->getBackendType(),
                        (string)$attribute->getSourceModel()
                    ),
                    'active' => true,
                    'required' => $required,
                    'has_custom_source' => $this->hasCustomSourceModel($attribute),
                    ]
                );
            }
        }

        $activeMap = $this->visibilityProvider->getActiveMap('attribute', 'magento', $codes);
        foreach ($attributes as &$attribute) {
            $attribute['active'] = $attribute['required'] || ($activeMap[$attribute['code']] ?? true);
        }
        unset($attribute);

        return $this->attributesCache[$cacheKey] = $attributes;
    }

    /**
     * @return array<string, array<string, bool|string>>
     */
    public function getAttributeMap(bool $includeExcluded = false): array
    {
        $cacheKey = $includeExcluded ? 'all' : 'visible';
        if (isset($this->attributeMapCache[$cacheKey])) {
            return $this->attributeMapCache[$cacheKey];
        }

        $attributes = [];
        foreach ($this->getAttributes($includeExcluded) as $attribute) {
            $attributes[$attribute['code']] = $attribute;
        }

        return $this->attributeMapCache[$cacheKey] = $attributes;
    }

    /**
     * @return array<string, bool|string>|null
     */
    public function getAttribute(string $code, bool $includeExcluded = false): ?array
    {
        return $this->getAttributeMap($includeExcluded)[$code] ?? null;
    }

    public function clearCache(): void
    {
        $this->attributesCache = [];
        $this->attributeMapCache = [];
        $this->attributeResources = [];
    }

    /**
     * @return string[]
     */
    public function getCustomSourceOptionValues(string $code): array
    {
        $attribute = $this->getAttributeResource($code);
        if (!$attribute || !$this->hasCustomSourceModel($attribute)) {
            return [];
        }

        try {
            $options = $attribute->getSource()->getAllOptions();
        } catch (Throwable) {
            return [];
        }

        return is_array($options) ? $this->extractOptionValues($options) : [];
    }

    private function isExcludedNativeAttribute(Attribute $attribute, string $code, string $label): bool
    {
        if (!$this->attributePolicy->isMappable($code)) {
            return true;
        }

        if ((bool)$attribute->getIsUserDefined()) {
            return false;
        }

        $normalizedLabel = $this->normalizeName($label);
        foreach (self::EXCLUDED_NATIVE_ATTRIBUTE_LABEL_FRAGMENTS as $fragment) {
            if ($fragment !== '' && str_contains($normalizedLabel, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($value))) ?? '';
    }

    private function getAttributeResource(string $code): ?Attribute
    {
        if (!isset($this->attributeResources[$code])) {
            $this->getAttributes(true);
        }

        return $this->attributeResources[$code] ?? null;
    }

    private function hasCustomSourceModel(Attribute $attribute): bool
    {
        if (trim((string)$attribute->getSourceModel()) === '') {
            return false;
        }

        try {
            return !$attribute->getSource() instanceof Table;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  array<int, array<string, mixed>> $options
     * @return string[]
     */
    private function extractOptionValues(array $options): array
    {
        $values = [];

        foreach ($options as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }

            if (is_array($option['value'])) {
                $values = array_merge($values, $this->extractOptionValues($option['value']));
                continue;
            }

            if (!is_scalar($option['value'])) {
                continue;
            }

            $value = trim((string)$option['value']);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function resolveScope(Attribute $attribute): string
    {
        if ($attribute->isScopeGlobal()) {
            return 'global';
        }

        if ($attribute->isScopeWebsite()) {
            return 'website';
        }

        return 'store view';
    }
}
