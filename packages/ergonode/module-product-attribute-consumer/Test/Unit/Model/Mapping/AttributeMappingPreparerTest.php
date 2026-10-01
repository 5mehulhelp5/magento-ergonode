<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingPreparer;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AttributeMappingPreparerTest extends TestCase
{
    public function testNormalizesCompleteMapping(): void
    {
        $ergonodeProvider = $this->createMock(ErgonodeAttributeProvider::class);
        $ergonodeProvider->expects(self::once())->method('getAttribute')->with('color')->willReturn(
            [
            'code' => 'color',
            'type' => 'select',
            ]
        );
        $magentoProvider = $this->createMock(MagentoAttributeProvider::class);
        $magentoProvider->expects(self::once())->method('getAttribute')->with('color')->willReturn(
            [
            'code' => 'color',
            'type' => 'select',
            ]
        );
        $attributePolicy = $this->createMock(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->expects(self::once())
            ->method('isErgonodeMappable')
            ->with('color')
            ->willReturn(true);
        $attributePolicy->expects(self::once())->method('isMappable')->with('color')->willReturn(true);
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::never())->method('previewFromErgonodeAttribute');
        $pendingAttributes = [];

        $result = (new AttributeMappingPreparer(
            $ergonodeProvider,
            $magentoProvider,
            $creator,
            $attributePolicy
        ))->prepare(
            [[
            'left' => ['code' => 'color'],
            'right' => ['code' => 'color'],
            ]],
            $pendingAttributes
        );

        self::assertSame([], $pendingAttributes);
        self::assertSame(
            [['left' => ['code' => 'color', 'type' => 'select'],
            'right' => ['code' => 'color', 'type' => 'select']]],
            $result
        );
    }

    public function testCollectsAttributeScheduledForMagentoCreation(): void
    {
        $source = ['code' => 'material', 'type' => 'select'];
        $ergonodeProvider = $this->createMock(ErgonodeAttributeProvider::class);
        $ergonodeProvider->expects(self::once())->method('getAttribute')->with('material')->willReturn($source);
        $magentoProvider = $this->createMock(MagentoAttributeProvider::class);
        $magentoProvider->expects(self::never())->method('getAttribute');
        $creator = $this->createMock(MagentoAttributeCreator::class);
        $creator->expects(self::once())->method('previewFromErgonodeAttribute')->with($source)->willReturn(
            [
            'code' => 'material',
            'type' => 'select',
            'created' => true,
            ]
        );
        $attributePolicy = $this->createMock(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->expects(self::once())
            ->method('isErgonodeMappable')
            ->with('material')
            ->willReturn(true);
        $attributePolicy->expects(self::once())->method('isMappable')->with('material')->willReturn(true);
        $pendingAttributes = [];

        $result = (new AttributeMappingPreparer(
            $ergonodeProvider,
            $magentoProvider,
            $creator,
            $attributePolicy
        ))->prepare(
            [[
            'left' => ['code' => 'material'],
            'right' => ['pending_create' => true],
            ]],
            $pendingAttributes
        );

        self::assertSame(['material' => $source], $pendingAttributes);
        self::assertTrue($result[0]['right']['created']);
    }

    public function testRejectsReservedErgonodeGalleryAttribute(): void
    {
        $ergonodeProvider = $this->createMock(ErgonodeAttributeProvider::class);
        $ergonodeProvider->expects(self::never())->method('getAttribute');
        $magentoProvider = $this->createMock(MagentoAttributeProvider::class);
        $magentoProvider->expects(self::never())->method('getAttribute');
        $attributePolicy = $this->createMock(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->expects(self::once())
            ->method('isErgonodeMappable')
            ->with('gallery')
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode attribute "gallery" is not available for mapping.');

        (new AttributeMappingPreparer(
            $ergonodeProvider,
            $magentoProvider,
            $this->createStub(MagentoAttributeCreator::class),
            $attributePolicy
        ))->prepare(
            [[
            'left' => ['code' => 'gallery'],
            'right' => ['code' => 'image'],
            ]]
        );
    }
}
