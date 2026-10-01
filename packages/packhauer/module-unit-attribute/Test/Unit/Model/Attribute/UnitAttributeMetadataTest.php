<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Test\Unit\Model\Attribute;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Model\Attribute\UnitAttributeMetadata;

class UnitAttributeMetadataTest extends TestCase
{
    public function testReadsNamespacedUnitAndPreservesSwatchDataWhenUpdating(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getData')->with('additional_data')->willReturn(
            '{"swatch_input_type":"visual","update_product_preview_image":"1",'
            . '"use_product_image_for_swatch":"0","unrelated":"keep",'
            . '"vendivo_unit":{"name":"CENTIMETER","symbol":"cm"}}'
        );
        $repository = $this->createMock(ProductAttributeRepositoryInterface::class);
        $repository->method('get')->with('length')->willReturn($attribute);
        $metadata = new UnitAttributeMetadata($repository, new Json());

        self::assertSame(
            ['name' => 'CENTIMETER', 'symbol' => 'cm'],
            $metadata->get('length')
        );
        self::assertSame(
            [
                'swatch_input_type' => 'visual',
                'update_product_preview_image' => '1',
                'use_product_image_for_swatch' => '0',
                'unrelated' => 'keep',
                'vendivo_unit' => ['name' => 'METER', 'symbol' => 'm'],
            ],
            json_decode(
                $metadata->withUnit(
                    '{"swatch_input_type":"visual","update_product_preview_image":"1",'
                    . '"use_product_image_for_swatch":"0","unrelated":"keep",'
                    . '"vendivo_unit":{"name":"CENTIMETER","symbol":"cm"}}',
                    'METER',
                    'm'
                ),
                true,
                flags: JSON_THROW_ON_ERROR
            )
        );
    }

    public function testRemovingUnitLeavesUnrelatedAdditionalDataUntouched(): void
    {
        $metadata = new UnitAttributeMetadata(
            $this->createStub(ProductAttributeRepositoryInterface::class),
            new Json()
        );

        self::assertSame(
            '{"swatch_input_type":"text"}',
            $metadata->withoutUnit(
                '{"swatch_input_type":"text","vendivo_unit":{"name":"METER","symbol":"m"}}'
            )
        );
    }
}
