<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute;

use InvalidArgumentException;

final readonly class LocalizedStringValues
{
    /** @var array<string, string> */
    private array $values;

    /** @param array<string, string> $values */
    public function __construct(array $values)
    {
        $normalized = [];
        foreach ($values as $language => $value) {
            $language = trim($language);
            if ($language === '') {
                throw new InvalidArgumentException('Remote product attribute language cannot be empty.');
            }
            if (!is_string($value)) {
                throw new InvalidArgumentException('Remote product string attribute values must be strings.');
            }
            $normalized[$language] = $value;
        }
        $this->values = $normalized;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }
}
