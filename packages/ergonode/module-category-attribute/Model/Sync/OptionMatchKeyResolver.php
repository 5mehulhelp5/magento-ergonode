<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Sync;

use Normalizer;

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
        $value = $this->normalize($value);
        $value = strtr(
            $value,
            [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
            ]
        );
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
        }

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
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
