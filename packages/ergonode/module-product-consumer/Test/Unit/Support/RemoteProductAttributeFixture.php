<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Support;

use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedNumberValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;

class RemoteProductAttributeFixture
{
    /** @param array<string, string> $values */
    public static function string(string $code, string $type, array $values): RemoteProductAttribute
    {
        return new RemoteProductAttribute(
            $code,
            new RemoteProductAttributeType($type),
            new LocalizedStringValues($values)
        );
    }

    /** @param array<string, float|int> $values */
    public static function number(string $code, string $type, array $values): RemoteProductAttribute
    {
        return new RemoteProductAttribute(
            $code,
            new RemoteProductAttributeType($type),
            new LocalizedNumberValues($values)
        );
    }

    /** @param array<string, list<string>> $values */
    public static function stringList(string $code, string $type, array $values): RemoteProductAttribute
    {
        return new RemoteProductAttribute(
            $code,
            new RemoteProductAttributeType($type),
            new LocalizedStringListValues($values)
        );
    }
}
