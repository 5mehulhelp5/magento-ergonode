<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute;

use InvalidArgumentException;

final readonly class LocalizedStringListValues
{
    /** @var array<string, list<string>> */
    private array $values;

    /** @param array<string, list<string>> $values */
    public function __construct(array $values)
    {
        $normalized = [];
        foreach ($values as $language => $items) {
            $language = trim($language);
            if ($language === '') {
                throw new InvalidArgumentException('Remote product attribute language cannot be empty.');
            }
            if (!is_array($items) || !array_is_list($items)) {
                throw new InvalidArgumentException('Remote product list attribute values must be lists.');
            }
            $list = [];
            foreach ($items as $item) {
                if (!is_string($item) || trim($item) === '') {
                    throw new InvalidArgumentException('Remote product attribute list values cannot be empty.');
                }
                $list[] = $item;
            }
            $normalized[$language] = $list;
        }
        $this->values = $normalized;
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->values;
    }
}
