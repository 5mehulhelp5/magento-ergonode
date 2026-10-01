<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\ValueObject\Product;

use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RemoteProductAttributeTest extends TestCase
{
    public function testRejectsValueShapeThatDoesNotMatchAttributeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('value shape does not match');

        new RemoteProductAttribute('color', new RemoteProductAttributeType('select'), new LocalizedStringListValues([
            'pl_PL' => ['red'],
        ]));
    }

    public function testNormalizesAttributeCodeWithoutChangingValues(): void
    {
        $attribute = new RemoteProductAttribute(
            ' name ',
            new RemoteProductAttributeType('text'),
            new LocalizedStringValues(['pl_PL' => 'Krzesło'])
        );

        self::assertSame([
            'code' => 'name',
            'type' => 'text',
            'values' => ['pl_PL' => 'Krzesło'],
        ], $attribute->normalized());
    }
}
