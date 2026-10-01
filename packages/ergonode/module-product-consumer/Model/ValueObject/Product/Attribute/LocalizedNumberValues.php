<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute;

use InvalidArgumentException;

final readonly class LocalizedNumberValues
{
    /** @var array<string, float|int> */
    private array $values;

    /** @param array<string, float|int> $values */
    public function __construct(array $values)
    {
        $normalized = [];
        foreach ($values as $language => $value) {
            $language = trim($language);
            if ($language === '') {
                throw new InvalidArgumentException('Remote product attribute language cannot be empty.');
            }
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Remote product numeric attribute values must be numbers.');
            }
            if (is_float($value) && !is_finite($value)) {
                throw new InvalidArgumentException('Remote product numeric attribute values must be finite.');
            }
            $normalized[$language] = $value;
        }
        $this->values = $normalized;
    }

    /** @return array<string, float|int> */
    public function all(): array
    {
        return $this->values;
    }
}
