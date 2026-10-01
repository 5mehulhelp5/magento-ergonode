<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Sync;

class OptionMatchKeyResolver
{
    private const string SOURCE_MAGENTO = 'magento';

    private const array TRUE_VALUES = ['1', 'true', 'yes', 'y', 'tak', 't', 'value1'];

    private const array FALSE_VALUES = ['0', 'false', 'no', 'n', 'nie', 'value0'];

    /**
     * @param array<string, mixed> $attributeMapping
     * @param array<string, mixed> $option
     */
    public function resolve(array $attributeMapping, array $option, string $source): string
    {
        $value = match (true) {
            $this->isVisibilityMapping($attributeMapping) => $this->visibilityValue($option, $source),
            $this->isBooleanMapping($attributeMapping) => $this->booleanValue($option, $source),
            default => $this->normalizeLabel((string)($option['label'] ?? '')),
        };
        $type = $this->normalize((string)($option['type'] ?? 'option'));

        return $value === '' ? '' : $type . "\0" . $value;
    }

    /**
     * @param array<string, mixed> $attributeMapping
     * @param array<string, mixed> $option
     */
    public function resolvePrimary(array $attributeMapping, array $option, string $source): string
    {
        if ($source === self::SOURCE_MAGENTO
            || $this->isVisibilityMapping($attributeMapping)
            || $this->isBooleanMapping($attributeMapping)
        ) {
            return $this->resolve($attributeMapping, $option, $source);
        }

        $value = $this->normalizeLabel((string)($option['code'] ?? ''));
        $type = $this->normalize((string)($option['type'] ?? 'option'));

        return $value === '' ? '' : $type . "\0" . $value;
    }

    public function normalizeLabel(string $value): string
    {
        return preg_replace('/\s+/u', '_', $this->normalize($value)) ?? '';
    }

    /**
     * @param array<string, mixed> $option
     */
    private function booleanValue(array $option, string $source): string
    {
        $candidates = $source === self::SOURCE_MAGENTO
            ? [$option['code'] ?? '', $option['scope'] ?? '', $option['label'] ?? '']
            : [$option['label'] ?? '', $option['code'] ?? '', $option['scope'] ?? ''];

        foreach ($candidates as $candidate) {
            $value = $this->normalizeLabel($this->withoutOptionPrefix((string)$candidate));
            if (in_array($value, self::TRUE_VALUES, true)) {
                return '1';
            }
            if (in_array($value, self::FALSE_VALUES, true)) {
                return '0';
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $option
     */
    private function visibilityValue(array $option, string $source): string
    {
        $candidates = $source === self::SOURCE_MAGENTO
            ? [$option['code'] ?? '', $option['scope'] ?? '']
            : [$option['label'] ?? '', $option['code'] ?? '', $option['scope'] ?? ''];

        foreach ($candidates as $candidate) {
            if (preg_match('/\d+/', $this->withoutOptionPrefix((string)$candidate), $matches)) {
                return $matches[0];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $attributeMapping
     */
    private function isVisibilityMapping(array $attributeMapping): bool
    {
        return $this->normalize((string)($attributeMapping['ergonode_attribute_code'] ?? '')) === 'visibility'
            && $this->normalize((string)($attributeMapping['magento_attribute_code'] ?? '')) === 'visibility';
    }

    /**
     * @param array<string, mixed> $attributeMapping
     */
    private function isBooleanMapping(array $attributeMapping): bool
    {
        return $this->normalize((string)($attributeMapping['magento_type'] ?? '')) === 'boolean';
    }

    private function withoutOptionPrefix(string $value): string
    {
        return preg_replace('/^option[_-]?/i', '', $this->normalize($value)) ?? '';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
