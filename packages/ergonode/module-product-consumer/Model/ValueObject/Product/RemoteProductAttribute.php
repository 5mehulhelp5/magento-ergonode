<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product;

use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedNumberValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use InvalidArgumentException;

final readonly class RemoteProductAttribute
{
    public string $code;

    public string $type;

    public function __construct(
        string $code,
        RemoteProductAttributeType $type,
        public LocalizedStringValues|LocalizedNumberValues|LocalizedStringListValues $values
    ) {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('Remote product attribute code cannot be empty.');
        }
        $validValues = match ($type->valueShape) {
            RemoteProductAttributeType::VALUE_SHAPE_STRING => $values instanceof LocalizedStringValues,
            RemoteProductAttributeType::VALUE_SHAPE_NUMBER => $values instanceof LocalizedNumberValues,
            RemoteProductAttributeType::VALUE_SHAPE_STRING_LIST => $values instanceof LocalizedStringListValues,
        };
        if (!$validValues) {
            throw new InvalidArgumentException('Remote product attribute value shape does not match its type.');
        }
        $this->code = $code;
        $this->type = $type->value;
    }

    /** @return array{code: string, type: string, values: array<string, float|int|string|list<string>>} */
    public function normalized(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'values' => $this->values->all(),
        ];
    }
}
