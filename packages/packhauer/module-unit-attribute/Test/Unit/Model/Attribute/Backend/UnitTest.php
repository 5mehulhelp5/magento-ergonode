<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Test\Unit\Model\Attribute\Backend;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;

class UnitTest extends TestCase
{
    public function testNormalizesACommaDecimalWithoutAppendingTheSymbol(): void
    {
        $backend = $this->backend();
        $product = new DataObject(['length' => '12,50']);

        $backend->beforeSave($product);

        self::assertSame('12.50', $product->getData('length'));
    }

    public function testRejectsAValueContainingAUnitSymbol(): void
    {
        $backend = $this->backend();

        $this->expectException(LocalizedException::class);
        $backend->beforeSave(new DataObject(['length' => '12 cm']));
    }

    private function backend(): Unit
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn('length');
        $backend = new Unit();
        $backend->setAttribute($attribute);

        return $backend;
    }
}
