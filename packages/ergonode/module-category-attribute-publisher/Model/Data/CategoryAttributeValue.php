<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Data;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeValueInterface;
use InvalidArgumentException;

class CategoryAttributeValue implements CategoryAttributeValueInterface
{
    /** @var array<string, float|string|string[]> */
    private array $translations;

    /** @param array<string, mixed> $translations */
    public function __construct(
        private readonly string $attributeCode,
        private readonly string $type,
        array $translations
    ) {
        if (trim($this->attributeCode) === '' || trim($this->type) === '') {
            throw new InvalidArgumentException('Category attribute code and type are required.');
        }
        $type = strtolower(trim($this->type));
        if (!in_array($type, ErgonodeAttributeTypeInterface::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported category attribute value type: ' . $type);
        }

        $normalized = [];
        foreach ($translations as $language => $value) {
            $language = trim((string)$language);
            if ($language === '' || array_key_exists($language, $normalized)) {
                throw new InvalidArgumentException(
                    'Category attribute translation languages must be unique and non-empty.'
                );
            }
            $normalized[$language] = $this->normalizeValue($type, $value);
        }
        ksort($normalized);
        $this->translations = $normalized;
    }

    public function getAttributeCode(): string
    {
        return trim($this->attributeCode);
    }
    public function getType(): string
    {
        return strtolower(trim($this->type));
    }
    public function getTranslations(): array
    {
        return $this->translations;
    }

    private function normalizeValue(string $type, mixed $value): float|string|array
    {
        if (in_array($type, ErgonodeAttributeTypeInterface::STRING_TYPES, true)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Category value type "%s" requires a string.', $type));
            }
            return $value;
        }
        if (in_array($type, ErgonodeAttributeTypeInterface::NUMERIC_TYPES, true)) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf('Category value type "%s" requires a number.', $type));
            }
            return (float)$value;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Category value type "%s" requires a list of strings.', $type));
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException(
                    sprintf('Category value type "%s" requires non-empty strings.', $type)
                );
            }
        }

        return array_values($value);
    }
}
