<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Magento\Framework\Exception\LocalizedException;
use PackHauer\FileAttribute\Api\FileStorageInterface;

class ProductAttributeValueMapper
{
    public function __construct(
        private readonly ProductAttributeMappingProviderInterface $mappingProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ErgonodeFileDownloaderInterface $fileDownloader,
        private readonly FileStorageInterface $fileStorage,
        private readonly ProductAttributeValueResolverPool $valueResolverPool,
        private readonly ProductAttributeMappingDeferrerPool $mappingDeferrers,
        private readonly ?ProductRelationIdentityTranslator $relationIdentityTranslator = null
    ) {
    }

    /**
     * @param RemoteProductAttribute[] $attributes
     * @param string[]|null $attributeCodes
     * @param array{
     *     values: array<string, array<int, float|int|string|string[]>>,
     *     clear: array<string, int[]>
     * }|null $resolvedSpecial
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     * @throws LocalizedException
     */
    public function map(array $attributes, ?array $attributeCodes = null, ?array $resolvedSpecial = null): array
    {
        return $this->mapMappings($attributes, false, $attributeCodes, $resolvedSpecial);
    }

    /**
     * Resolve only attributes handled by a dedicated consumer resolver.
     *
     * @param RemoteProductAttribute[] $attributes
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     * @throws LocalizedException
     */
    public function mapSpecial(array $attributes): array
    {
        return $this->mapMappings($attributes, true);
    }

    /**
     * @param RemoteProductAttribute[] $attributes
     * @param string[]|null $attributeCodes
     * @param array{
     *     values: array<string, array<int, float|int|string|string[]>>,
     *     clear: array<string, int[]>
     * }|null $resolvedSpecial
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     * @throws LocalizedException
     */
    private function mapMappings(
        array $attributes,
        bool $specialOnly,
        ?array $attributeCodes = null,
        ?array $resolvedSpecial = null
    ): array {
        $this->valueResolverPool->resetResolutionScope();
        try {
            return $this->mapInScope($attributes, $specialOnly, $attributeCodes, $resolvedSpecial);
        } finally {
            $this->valueResolverPool->resetResolutionScope();
        }
    }

    /**
     * @param RemoteProductAttribute[] $attributes
     * @param string[]|null $attributeCodes
     * @param array{
     *     values: array<string, array<int, float|int|string|string[]>>,
     *     clear: array<string, int[]>
     * }|null $resolvedSpecial
     * @return array{values: array<string, array<int, float|int|string|string[]>>, clear: array<string, int[]>}
     */
    private function mapInScope(
        array $attributes,
        bool $specialOnly,
        ?array $attributeCodes,
        ?array $resolvedSpecial
    ): array {
        $source = [];
        foreach ($attributes as $attribute) {
            $source[$attribute->code] = $attribute;
        }
        $storeLanguages = $this->languageMappingProvider->getLanguageStoreMap();
        $values = $resolvedSpecial['values'] ?? [];
        $clear = $resolvedSpecial['clear'] ?? [];
        if ($attributeCodes !== null) {
            $selected = array_fill_keys($attributeCodes, true);
            $values = array_intersect_key($values, $selected);
            $clear = array_intersect_key($clear, $selected);
        }
        $resolvedCodes = $values + $clear;
        foreach ($this->mappingProvider->getMappings() as $mapping) {
            $sourceCode = (string)$mapping['ergonode_attribute_code'];
            $targetCode = (string)$mapping['magento_attribute_code'];
            if ($attributeCodes !== null && !in_array($targetCode, $attributeCodes, true)) {
                continue;
            }
            if (array_key_exists($targetCode, $resolvedCodes)) {
                continue;
            }
            $resolver = $this->valueResolverPool->get($mapping);
            if ($specialOnly && $resolver === null) {
                continue;
            }
            if ($targetCode === 'sku') {
                continue;
            }
            if ($this->mappingDeferrers->isDeferred($mapping)) {
                continue;
            }
            foreach ($storeLanguages as $storeId => $languageCode) {
                $translations = isset($source[$sourceCode]) ? $source[$sourceCode]->values->all() : [];
                if (!array_key_exists($languageCode, $translations)) {
                    if ($resolver !== null) {
                        $resolver->resolve(null, $mapping, $languageCode, (int)$storeId);
                    }
                    $clear[$targetCode][] = (int)$storeId;
                    continue;
                }
                $mapped = $resolver !== null
                    ? $resolver->resolve($translations[$languageCode], $mapping, $languageCode, (int)$storeId)
                    : $this->mapValue($translations[$languageCode], $mapping, $languageCode);
                if ($mapped === null || $mapped === '' || $mapped === []) {
                    $clear[$targetCode][] = (int)$storeId;
                    continue;
                }
                if ($targetCode === 'name' && mb_strlen((string)$mapped) > 255) {
                    throw new LocalizedException(__('Mapped product name must not exceed 255 characters.'));
                }
                $values[$targetCode][(int)$storeId] = $mapped;
            }
        }
        foreach ($clear as &$storeIds) {
            $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        }
        unset($storeIds);

        return ['values' => $values, 'clear' => $clear];
    }

    /** @param float|int|string|string[] $value @param array<string, mixed> $mapping */
    private function mapValue(
        float|int|string|array $value,
        array $mapping,
        string $languageCode
    ): float|int|string|array|null {
        $sourceType = $this->normalizeType((string)$mapping['ergonode_type']);
        $targetType = $this->normalizeType((string)$mapping['magento_type']);
        if ($sourceType === 'relation') {
            if ($this->relationIdentityTranslator === null) {
                throw new LocalizedException(__('Product relation identity translation is unavailable.'));
            }
            $value = $this->relationIdentityTranslator->toMagentoSkus($this->valueList($value));
        }
        if ($targetType === 'boolean') {
            return $this->mapBoolean($value, $mapping);
        }
        if (in_array($sourceType, ['file', 'gallery', 'image'], true)) {
            $value = $this->mapFiles($value, $targetType, (string)$mapping['magento_attribute_code']);
        }
        if (in_array($sourceType, ['select', 'multiselect'], true)
            && in_array($targetType, ['select', 'multiselect'], true)
        ) {
            return $this->mapOptionIds($value, $mapping, $targetType);
        }
        if (in_array($sourceType, ['text', 'textarea'], true)
            && in_array($targetType, ['select', 'multiselect'], true)
        ) {
            return $this->mapTextToOptionIds($value, $mapping, $targetType);
        }
        if (in_array($sourceType, ['select', 'multiselect'], true)
            && in_array($targetType, ['text', 'textarea'], true)
        ) {
            $value = $this->mapOptionLabels($value, $mapping, $languageCode);
        }
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }
        if (in_array($targetType, ['decimal', 'price', 'unit'], true)) {
            $numeric = str_replace(',', '.', trim((string)$value));
            if ($targetType === 'price' && is_numeric($numeric) && (float)$numeric < 0) {
                throw new LocalizedException(__('Mapped product price must be a non-negative decimal number.'));
            }

            return is_numeric($numeric) ? $numeric : null;
        }
        if ($targetType === 'textarea') {
            return $this->filterTextarea((string)$value);
        }

        return $value;
    }

    /** @param float|int|string|string[] $value @return string|string[]|null */
    private function mapFiles(
        float|int|string|array $value,
        string $targetType,
        string $attributeCode
    ): string|array|null {
        $sources = is_array($value) ? array_values($value) : [$value];
        $paths = [];
        $mediaDirectory = $targetType === 'file'
            ? $this->fileStorage->getPermanentDirectory($attributeCode)
            : 'catalog/product/ergonode';
        foreach ($sources as $source) {
            $source = trim((string)$source);
            if ($source === '') {
                continue;
            }
            $file = $this->fileDownloader->download($source, $mediaDirectory);
            $paths[] = in_array($targetType, ['file', 'image'], true)
                ? $file['relative_path']
                : $file['media_url'];
        }
        $paths = array_values(array_unique($paths));
        if ($paths === []) {
            return null;
        }

        return $targetType === 'multiselect' ? $paths : ($paths[1] ?? null ? $paths : $paths[0]);
    }

    /** @param float|int|string|string[] $value @param array<string, mixed> $mapping */
    private function mapBoolean(float|int|string|array $value, array $mapping): ?int
    {
        $candidate = is_array($value) ? reset($value) : $value;
        if (is_string($candidate) && array_key_exists($candidate, (array)$mapping['option_ids'])) {
            return (int)$mapping['option_ids'][$candidate] > 0 ? 1 : 0;
        }
        if (is_bool($candidate) || in_array($candidate, [0, 1, '0', '1'], true)) {
            return (int)(bool)$candidate;
        }
        $candidate = mb_strtolower(trim((string)$candidate));
        if (in_array($candidate, ['true', 'yes', 'y', 'tak'], true)) {
            return 1;
        }
        if (in_array($candidate, ['false', 'no', 'n', 'nie'], true)) {
            return 0;
        }

        return null;
    }

    /** @param float|int|string|string[] $value @param array<string, mixed> $mapping */
    private function mapOptionIds(float|int|string|array $value, array $mapping, string $targetType): ?string
    {
        $ids = [];
        foreach ($this->valueList($value) as $code) {
            if (!array_key_exists($code, (array)$mapping['option_ids'])) {
                return null;
            }
            $ids[] = (int)$mapping['option_ids'][$code];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return null;
        }

        return $targetType === 'select' ? (string)$ids[0] : implode(',', $ids);
    }

    /** @param float|int|string|string[] $value @param array<string, mixed> $mapping */
    private function mapTextToOptionIds(float|int|string|array $value, array $mapping, string $targetType): ?string
    {
        $labels = is_array($value)
            ? array_map('strval', $value)
            : ($targetType === 'multiselect' ? preg_split('/[;,|\n]+/', (string)$value) ?: [] : [(string)$value]);
        $ids = [];
        foreach ($labels as $label) {
            $key = $this->normalizeOptionLabel((string)$label);
            if ($key === '' || !isset($mapping['magento_option_ids_by_label'][$key])) {
                return null;
            }
            $ids[] = (int)$mapping['magento_option_ids_by_label'][$key];
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return null;
        }

        return $targetType === 'select' ? (string)$ids[0] : implode(',', $ids);
    }

    /** @param float|int|string|string[] $value @param array<string, mixed> $mapping */
    private function mapOptionLabels(float|int|string|array $value, array $mapping, string $languageCode): ?string
    {
        $labels = [];
        $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();
        foreach ($this->valueList($value) as $code) {
            $translations = (array)($mapping['option_labels'][$code] ?? []);
            $labels[] = (string)($translations[$languageCode]
                ?? ($adminLanguage !== null ? $translations[$adminLanguage] ?? null : null)
                ?? $code);
        }

        return $labels !== [] ? implode(', ', $labels) : null;
    }

    /** @param float|int|string|string[] $value @return string[] */
    private function valueList(float|int|string|array $value): array
    {
        return array_values(array_filter(array_map(
            static fn (float|int|string $item): string => trim((string)$item),
            is_array($value) ? $value : [$value]
        )));
    }

    private function normalizeType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'multi_select' => 'multiselect',
            'product_relation' => 'relation',
            default => strtolower(trim($type)),
        };
    }

    private function normalizeOptionLabel(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($ascii) && $ascii !== '') {
            $label = $ascii;
        }

        return preg_replace('/[^a-z0-9]+/', '', $label) ?? '';
    }

    private function filterTextarea(string $value): string
    {
        $value = htmlspecialchars_decode($value);
        $value = strip_tags($value, [
            'div', 'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'span', 'strong', 'em', 'ul', 'li', 'ol', 'b', 'a', 'small', 'i',
        ]);

        return trim($value);
    }
}
