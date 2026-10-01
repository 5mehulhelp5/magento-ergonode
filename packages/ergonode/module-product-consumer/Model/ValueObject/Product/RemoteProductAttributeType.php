<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\ValueObject\Product;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use InvalidArgumentException;

final readonly class RemoteProductAttributeType
{
    public const string VALUE_SHAPE_STRING = 'string';
    public const string VALUE_SHAPE_NUMBER = 'number';
    public const string VALUE_SHAPE_STRING_LIST = 'string_list';

    private const array STRING_TYPES = [
        ErgonodeAttributeTypeInterface::TYPE_DATE,
        ErgonodeAttributeTypeInterface::TYPE_FILE,
        ErgonodeAttributeTypeInterface::TYPE_IMAGE,
        ErgonodeAttributeTypeInterface::TYPE_SELECT,
        ErgonodeAttributeTypeInterface::TYPE_TEXT,
        ErgonodeAttributeTypeInterface::TYPE_TEXTAREA,
    ];

    private const array LIST_TYPES = [
        ErgonodeAttributeTypeInterface::TYPE_GALLERY,
        ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT,
        ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION,
    ];

    public string $valueShape;

    public function __construct(public string $value)
    {
        $this->valueShape = match (true) {
            in_array($value, self::STRING_TYPES, true) => self::VALUE_SHAPE_STRING,
            in_array($value, ErgonodeAttributeTypeInterface::NUMERIC_TYPES, true) => self::VALUE_SHAPE_NUMBER,
            in_array($value, self::LIST_TYPES, true) => self::VALUE_SHAPE_STRING_LIST,
            default => throw new InvalidArgumentException('Remote product attribute type is unsupported.'),
        };
    }
}
