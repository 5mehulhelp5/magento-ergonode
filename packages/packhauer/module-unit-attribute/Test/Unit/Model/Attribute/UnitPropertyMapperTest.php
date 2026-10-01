<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Test\Unit\Model\Attribute;

use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;
use PackHauer\UnitAttribute\Model\Attribute\UnitPropertyMapper;

class UnitPropertyMapperTest extends TestCase
{
    public function testMapsStorageAndAdditionalDataOnlyForUnitAttributes(): void
    {
        $mapper = new UnitPropertyMapper();

        self::assertSame(
            [
                'backend_model' => Unit::class,
                'backend_type' => 'decimal',
                'additional_data' => '{"vendivo_unit":{"name":"METER","symbol":"m"}}',
            ],
            $mapper->map([
                'input' => 'unit',
                'additional_data' => '{"vendivo_unit":{"name":"METER","symbol":"m"}}',
            ], 4)
        );
        self::assertSame([], $mapper->map(['input' => 'text', 'additional_data' => '{}'], 4));
    }
}
