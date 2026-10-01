<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Block\Adminhtml\Attribute;

use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Block\Adminhtml\Attribute\Mapping;
use Ergonode\ProductAttributeAdminUi\Model\AttributeListSorter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MappingTest extends TestCase
{
    public function testGalleryAttributesAreAbsentFromTheMappingList(): void
    {
        $provider = $this->createStub(ErgonodeMetadataProviderInterface::class);
        $provider->method('getAttributes')->willReturn([
            ['label' => 'Test gallery', 'code' => 'test_gallery', 'type' => 'gallery'],
            ['label' => 'Other gallery', 'code' => 'other_gallery', 'type' => ' GALLERY '],
            ['label' => 'Image', 'code' => 'image', 'type' => 'image'],
            ['label' => 'Title', 'code' => 'title', 'type' => 'text'],
        ]);

        $reflection = new ReflectionClass(Mapping::class);
        $block = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('ergonodeAttributeProvider')->setValue($block, $provider);
        $reflection->getProperty('attributeListSorter')->setValue($block, new AttributeListSorter());

        self::assertSame(['image', 'title'], array_column($block->getErgonodeAttributes(), 'code'));
    }
}
