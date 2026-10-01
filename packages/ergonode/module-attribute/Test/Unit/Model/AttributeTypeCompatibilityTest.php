<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Test\Unit\Model\Mapping;

use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use PHPUnit\Framework\TestCase;

class AttributeTypeCompatibilityTest extends TestCase
{
    public function testUnitCanUseNumericOrTextMagentoStorage(): void
    {
        $compatibility = new AttributeTypeCompatibility();

        self::assertTrue($compatibility->canMapAttributes('unit', 'unit'));
        self::assertTrue($compatibility->canMapAttributes('unit', 'decimal'));
        self::assertTrue($compatibility->canMapAttributes('unit', 'text'));
        self::assertTrue($compatibility->canMapAttributes('unit', 'textarea'));
    }

    public function testPriceCanUseNumericOrTextMagentoStorage(): void
    {
        $compatibility = new AttributeTypeCompatibility();

        self::assertTrue($compatibility->canMapAttributes('price', 'price'));
        self::assertTrue($compatibility->canMapAttributes('price', 'decimal'));
        self::assertTrue($compatibility->canMapAttributes('price', 'text'));
    }

    public function testNumericCanUseMagentoDecimalStorage(): void
    {
        self::assertTrue((new AttributeTypeCompatibility())->canMapAttributes('numeric', 'decimal'));
    }

    public function testGalleryAndRelationNeverUseOrdinaryAttributeMapping(): void
    {
        $compatibility = new AttributeTypeCompatibility();

        self::assertFalse($compatibility->canMapAttributes('gallery', 'image'));
        self::assertFalse($compatibility->canMapAttributes('relation', 'text'));
    }

    public function testPublishedCompatibilityMapDrivesEverySupportedAttributePair(): void
    {
        $compatibility = new AttributeTypeCompatibility();

        foreach ($compatibility->getAttributeCompatibilityMap() as $ergonodeType => $magentoTypes) {
            foreach ($magentoTypes as $magentoType) {
                self::assertTrue(
                    $compatibility->canMapAttributes($ergonodeType, $magentoType),
                    sprintf('Expected %s to be compatible with %s.', $ergonodeType, $magentoType)
                );
            }
        }

        self::assertFalse($compatibility->canMapAttributes('numeric', 'text'));
        self::assertFalse($compatibility->canMapAttributes('unknown', 'unknown'));
    }
}
