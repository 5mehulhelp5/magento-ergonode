<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueClearIntentInterface;
use InvalidArgumentException;

final readonly class ProductAttributeValue implements ProductAttributeValueClearIntentInterface
{
    private const array NON_EMPTY_STRING_TYPES = [
        ErgonodeAttributeTypeInterface::TYPE_DATE,
        ErgonodeAttributeTypeInterface::TYPE_IMAGE,
        ErgonodeAttributeTypeInterface::TYPE_SELECT,
    ];
    private const array TWO_WAY_RELATIONS = ['All', 'New', 'None'];

    /** @var array<string, float|string|string[]> */
    private array $translations;

    private string $attributeCode;

    private string $type;

    private ?string $twoWayRelation;

    /** @var string[] */
    private array $clearedLanguageCodes;

    /**
     * @param array<string, float|int|string|string[]> $translations
     * @param string[] $clearedLanguageCodes
     */
    public function __construct(
        string $attributeCode,
        string $type,
        array $translations,
        ?string $twoWayRelation = null,
        array $clearedLanguageCodes = []
    ) {
        $this->attributeCode = trim($attributeCode);
        $this->type = $type;
        $this->twoWayRelation = $twoWayRelation;
        if ($this->attributeCode === '') {
            throw new InvalidArgumentException('Product attribute code cannot be empty.');
        }
        if (!in_array($this->type, ErgonodeAttributeTypeInterface::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported product attribute value type: ' . $this->type);
        }
        if ($this->twoWayRelation !== null
            && ($this->type !== ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION
                || !in_array($this->twoWayRelation, self::TWO_WAY_RELATIONS, true))
        ) {
            throw new InvalidArgumentException(
                'Two-way relation is valid only for product relations and must match the schema enum.'
            );
        }
        $normalized = [];
        foreach ($translations as $language => $value) {
            $language = trim((string)$language);
            if ($language === '') {
                throw new InvalidArgumentException('Product attribute translation language cannot be empty.');
            }
            $normalized[$language] = $this->normalizeValue($value);
        }
        ksort($normalized);
        $this->translations = $normalized;
        $cleared = [];
        foreach ($clearedLanguageCodes as $language) {
            if (!is_string($language) || trim($language) === '') {
                throw new InvalidArgumentException('Cleared product value languages cannot be empty.');
            }
            $language = trim($language);
            if (array_key_exists($language, $normalized)) {
                throw new InvalidArgumentException(
                    'A product value language cannot contain both a translation and explicit clear intent.'
                );
            }
            $cleared[] = $language;
        }
        $cleared = array_values(array_unique($cleared));
        sort($cleared);
        $this->clearedLanguageCodes = $cleared;
    }

    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getTwoWayRelation(): ?string
    {
        return $this->twoWayRelation;
    }

    public function getClearedLanguageCodes(): array
    {
        return $this->clearedLanguageCodes;
    }

    /** @return float|string|string[] */
    private function normalizeValue(mixed $value): float|string|array
    {
        if (in_array($this->type, ErgonodeAttributeTypeInterface::LIST_TYPES, true)) {
            if (!is_array($value)) {
                throw new InvalidArgumentException('Product list attribute translations must contain string lists.');
            }
            $normalized = [];
            foreach ($value as $item) {
                if (!is_string($item) || trim($item) === '') {
                    throw new InvalidArgumentException('Product list attribute items cannot be empty.');
                }
                $normalized[] = trim($item);
            }

            $normalized = array_values(array_unique($normalized));
            if (in_array($this->type, [
                ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT,
                ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION,
            ], true)) {
                sort($normalized);
            }

            return $normalized;
        }
        if (in_array($this->type, ErgonodeAttributeTypeInterface::NUMERIC_TYPES, true)) {
            if ((!is_int($value) && !is_float($value)) || (is_float($value) && !is_finite($value))) {
                throw new InvalidArgumentException('Product numeric attribute translations must be finite numbers.');
            }

            return (float)$value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Product scalar attribute translations must be strings.');
        }
        if (in_array($this->type, self::NON_EMPTY_STRING_TYPES, true) && trim($value) === '') {
            throw new InvalidArgumentException('Product reference, select and date translations cannot be empty.');
        }

        return $value;
    }
}
