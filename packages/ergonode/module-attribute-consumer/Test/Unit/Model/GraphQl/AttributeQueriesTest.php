<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\GraphQl;

use Ergonode\AttributeConsumer\Model\GraphQl\AttributeQueries;
use PHPUnit\Framework\TestCase;

class AttributeQueriesTest extends TestCase
{
    public function testAttributeStreamRequestsTypeSpecificParameters(): void
    {
        self::assertStringContainsString('... on NumericAttribute', AttributeQueries::ATTRIBUTE_STREAM);
        self::assertStringContainsString('... on PriceAttribute', AttributeQueries::ATTRIBUTE_STREAM);
        self::assertStringContainsString('... on UnitAttribute', AttributeQueries::ATTRIBUTE_STREAM);
        self::assertStringContainsString(
            "unit {\n            name\n            symbol",
            AttributeQueries::ATTRIBUTE_STREAM
        );
        self::assertStringNotContainsString('metadata {', AttributeQueries::ATTRIBUTE_STREAM);
    }
}
