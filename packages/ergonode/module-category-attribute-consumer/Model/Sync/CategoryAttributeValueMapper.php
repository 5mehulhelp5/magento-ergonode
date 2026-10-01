<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\OptionLabelKeyNormalizer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CategoryAttributeValueMapper
{
    public function __construct(
        private readonly CategoryAttributeMappingProviderInterface $mappingProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ErgonodeFileDownloaderInterface $fileDownloader,
        private readonly LoggerInterface $logger,
        private readonly CategoryAttributePolicy $attributePolicy,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly OptionLabelKeyNormalizer $optionLabelKeyNormalizer
    ) {
    }

    /**
     * @param array<int, array{code: string, type: string, values: array<string, mixed>}> $attributes
     * @return array<string, array<int, mixed>>
     */
    public function map(array $attributes, array $targetAttributeCodes = []): array
    {
        $source = $this->indexAttributes($attributes);
        $storeLanguages = $this->languageMappingProvider->getLanguageStoreMap();
        ksort($storeLanguages);
        $targetAttributeCodes = array_fill_keys(array_filter(array_map('trim', $targetAttributeCodes)), true);
        $result = [];
        foreach ($this->mappingProvider->getValueMappings() as $mapping) {
            $sourceCode = (string)$mapping['ergonode_attribute_code'];
            $targetCode = (string)$mapping['magento_attribute_code'];
            if (($targetAttributeCodes !== [] && !isset($targetAttributeCodes[$targetCode]))
                || !isset($source[$sourceCode])
                || !$this->attributePolicy->isMappable($targetCode)
            ) {
                continue;
            }

            foreach ($storeLanguages as $storeId => $languageCode) {
                $sourceValue = $this->resolveLanguageValue(
                    (array)($source[$sourceCode]['values'] ?? []),
                    (string)$languageCode
                );
                $mappedValue = $this->mapValue($sourceValue, $mapping, (string)$languageCode);
                if ($mappedValue !== null && $mappedValue !== '' && $mappedValue !== []) {
                    $result[$targetCode][(int)$storeId] = $mappedValue;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int, array{code: string, type: string, values: array<string, mixed>}> $attributes
     * @return array{
     *     values: array<string, array<int, mixed>>,
     *     clear: array<string, int[]>
     * }
     */
    public function mapForSynchronization(array $attributes): array
    {
        $source = $this->indexAttributes($attributes);
        $storeLanguages = $this->languageMappingProvider->getLanguageStoreMap();
        ksort($storeLanguages);
        $values = [];
        $clear = [];
        foreach ($this->mappingProvider->getValueMappings() as $mapping) {
            $sourceCode = (string)$mapping['ergonode_attribute_code'];
            $targetCode = (string)$mapping['magento_attribute_code'];
            if (!$this->attributePolicy->isMappable($targetCode)) {
                continue;
            }
            if (!isset($source[$sourceCode])) {
                $clear[$targetCode] = array_map('intval', array_keys($storeLanguages));
                continue;
            }
            $sourceValues = (array)($source[$sourceCode]['values'] ?? []);
            foreach ($storeLanguages as $storeId => $languageCode) {
                $sourceValue = $this->resolveSynchronizationValue(
                    $sourceValues,
                    (int)$storeId,
                    (string)$languageCode
                );
                if (!$sourceValue['present']
                    || $sourceValue['value'] === null
                    || $sourceValue['value'] === ''
                    || $sourceValue['value'] === []
                ) {
                    $clear[$targetCode][] = (int)$storeId;
                    continue;
                }
                $mappedValue = $this->mapValue($sourceValue['value'], $mapping, (string)$languageCode);
                if ($mappedValue !== null && $mappedValue !== '' && $mappedValue !== []) {
                    $values[$targetCode][(int)$storeId] = $mappedValue;
                }
            }
        }
        foreach ($clear as &$storeIds) {
            $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        }
        unset($storeIds);

        return ['values' => $values, 'clear' => $clear];
    }

    /**
     * @param array<int, array{code: string, type: string, values: array<string, mixed>}> $attributes
     * @return array<string, array{code: string, type: string, values: array<string, mixed>}>
     */
    private function indexAttributes(array $attributes): array
    {
        $source = [];
        foreach ($attributes as $attribute) {
            $code = trim((string)($attribute['code'] ?? ''));
            if ($code !== '') {
                $source[$code] = $attribute;
            }
        }

        return $source;
    }

    /** @param array<string, mixed> $values @return array{present: bool, value: mixed} */
    private function resolveSynchronizationValue(array $values, int $storeId, string $languageCode): array
    {
        if (array_key_exists($languageCode, $values)) {
            return ['present' => true, 'value' => $values[$languageCode]];
        }
        $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();
        if ($storeId === 0 && $adminLanguage !== null && array_key_exists($adminLanguage, $values)) {
            return ['present' => true, 'value' => $values[$adminLanguage]];
        }

        return ['present' => false, 'value' => null];
    }

    /** @param array<string, mixed> $values */
    private function resolveLanguageValue(array $values, string $languageCode): mixed
    {
        if (array_key_exists($languageCode, $values)) {
            return $values[$languageCode];
        }

        $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();

        return $adminLanguage !== null && array_key_exists($adminLanguage, $values)
            ? $values[$adminLanguage]
            : null;
    }

    /** @param array<string, mixed> $mapping */
    private function mapValue(mixed $value, array $mapping, string $languageCode): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $sourceType = $this->typeResolver->toConsumerType((string)$mapping['ergonode_type']);
        $targetType = $this->typeResolver->toConsumerType((string)$mapping['magento_type']);
        if ($targetType === 'boolean') {
            return $this->mapBoolean($value, $mapping);
        }
        if (in_array($sourceType, ['file', 'image'], true)) {
            return $this->mapFileValue($value, $targetType);
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

            return is_numeric($numeric) ? $numeric : null;
        }
        if ($targetType === 'textarea') {
            return $this->filterTextarea((string)$value);
        }

        return $value;
    }

    private function mapFileValue(mixed $value, string $targetType): ?string
    {
        $source = is_array($value) ? (string)reset($value) : (string)$value;
        if (trim($source) === '') {
            return null;
        }

        try {
            $directory = $targetType === 'image' ? 'catalog/category/ergonode' : 'ergonode/file';
            $file = $this->fileDownloader->download($source, $directory);
        } catch (LocalizedException $exception) {
            $this->logger->warning('Unable to download mapped Ergonode category file.', [
                'source' => $source,
                'target_type' => $targetType,
                'exception' => $exception,
            ]);

            return null;
        }

        if ($targetType === 'image') {
            return preg_replace('#^catalog/category/#', '', $file['relative_path']) ?: null;
        }

        return $targetType === 'file' ? $file['relative_path'] : $file['media_url'];
    }

    /** @param array<string, mixed> $mapping */
    private function mapBoolean(mixed $value, array $mapping): ?int
    {
        $candidate = is_array($value) ? reset($value) : $value;
        if (is_string($candidate) && array_key_exists($candidate, (array)$mapping['option_ids'])) {
            return (int)$mapping['option_ids'][$candidate] > 0 ? 1 : 0;
        }
        if (is_bool($candidate) || $candidate === 0 || $candidate === 1 || $candidate === '0' || $candidate === '1') {
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

    /** @param array<string, mixed> $mapping */
    private function mapOptionIds(mixed $value, array $mapping, string $targetType): ?string
    {
        $codes = $this->codeList($value);
        $ids = [];
        foreach ($codes as $code) {
            if (!array_key_exists($code, (array)$mapping['option_ids'])) {
                return null;
            }
            $ids[] = (int)$mapping['option_ids'][$code];
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return null;
        }

        return $targetType === 'select' ? (string)$ids[0] : implode(',', $ids);
    }

    /** @param array<string, mixed> $mapping */
    private function mapTextToOptionIds(mixed $value, array $mapping, string $targetType): ?string
    {
        $labels = is_array($value)
            ? array_map('strval', $value)
            : ($targetType === 'multiselect' ? preg_split('/[;,|\n]+/', (string)$value) ?: [] : [(string)$value]);
        $ids = [];
        foreach ($labels as $label) {
            $key = $this->optionLabelKeyNormalizer->normalize((string)$label);
            if ($key === '' || !isset($mapping['magento_option_ids_by_label'][$key])) {
                return null;
            }
            $ids[] = (int)$mapping['magento_option_ids_by_label'][$key];
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return null;
        }

        return $targetType === 'select' ? (string)$ids[0] : implode(',', $ids);
    }

    /** @param array<string, mixed> $mapping */
    private function mapOptionLabels(mixed $value, array $mapping, string $languageCode): ?string
    {
        $labels = [];
        $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();
        foreach ($this->codeList($value) as $code) {
            $translations = (array)($mapping['option_labels'][$code] ?? []);
            $labels[] = (string)($translations[$languageCode]
                ?? ($adminLanguage !== null ? $translations[$adminLanguage] ?? null : null)
                ?? $code);
        }

        return $labels !== [] ? implode(', ', $labels) : null;
    }

    /** @return string[] */
    private function codeList(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string)$item),
            $values
        )));
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
