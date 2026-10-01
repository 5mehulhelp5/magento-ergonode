<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Model\Exception\MissingOptionMappingException;
use Magento\Framework\Exception\LocalizedException;

class ProductValueNormalizer
{
    /** @param array<string, int> $optionIds @return float|string|string[]|null
     * @throws LocalizedException
     */
    public function normalize(mixed $value, string $type, array $optionIds, string $context): float|string|array|null
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($type) {
            'select' => $this->optionCode($value, $optionIds, $context),
            'multi_select' => $this->optionCodes($value, $optionIds, $context),
            'numeric', 'price', 'unit' => $this->number($value, $context),
            'file', 'gallery', 'product_relation' => $this->strings($value),
            'date', 'image', 'text', 'textarea' => $this->string($value, $context),
            default => throw new LocalizedException(__(
                'Ergonode product value type "%1" is unsupported for %2.',
                $type,
                $context
            )),
        };
    }

    /**
     * @param array<string, int> $optionIds
     * @throws LocalizedException
     */
    private function optionCode(mixed $value, array $optionIds, string $context): string
    {
        $code = array_search((int)$value, $optionIds, true);
        if (!is_string($code)) {
            throw new MissingOptionMappingException([(string)$value], $context);
        }

        return $code;
    }

    /**
     * @param array<string, int> $optionIds @return string[]
     * @throws LocalizedException
     */
    private function optionCodes(mixed $value, array $optionIds, string $context): array
    {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $result = [];
        $missing = [];
        foreach ($values as $optionId) {
            if (trim((string)$optionId) !== '') {
                try {
                    $result[] = $this->optionCode($optionId, $optionIds, $context);
                } catch (MissingOptionMappingException $exception) {
                    $missing[(string)$optionId] = true;
                }
            }
        }
        if ($missing !== []) {
            $ids = array_map('strval', array_keys($missing));
            sort($ids);
            throw new MissingOptionMappingException($ids, $context);
        }
        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    /**
     * @throws LocalizedException
     */
    private function number(mixed $value, string $context): float
    {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            throw new LocalizedException(__('Magento value for %1 must be a finite number.', $context));
        }

        return (float)$value;
    }

    /** @return string[] */
    private function strings(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $result = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string)$item),
            $values
        ))));
        sort($result);

        return $result;
    }

    /**
     * @throws LocalizedException
     */
    private function string(mixed $value, string $context): string
    {
        if (!is_scalar($value)) {
            throw new LocalizedException(__('Magento value for %1 must be scalar.', $context));
        }

        return (string)$value;
    }
}
