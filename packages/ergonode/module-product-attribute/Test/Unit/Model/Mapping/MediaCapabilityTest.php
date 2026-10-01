<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Mapping;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Ergonode\ProductAttribute\Model\ProductAttributePlacementPolicy;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\Attribute\Model\Mapping\AttributeTypeCompatibility;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class MediaCapabilityTest extends TestCase
{
    public function testImageNeedsOptionalCapabilityAndGalleryIsAlwaysExcluded(): void
    {
        foreach ([false, true] as $enabled) {
            $policy = new ProductAttributePolicy(
                $this->createStub(ScopeConfigInterface::class),
                new ProductAttributePlacementPolicy(),
                $this->createStub(ProductIdentityModeProviderInterface::class),
                $this->createStub(MagentoIdentityAttributeInterface::class),
                $enabled ? ['image' => true] : []
            );
            $compatibility = new ProductAttributeMappingCompatibility(new AttributeTypeCompatibility(), $policy);
            self::assertSame($enabled, $compatibility->canMapAttributes('image', 'image', 'detail_image'));
            self::assertFalse($compatibility->canMapAttributes('image', 'text', 'detail_text'));
            self::assertFalse($compatibility->canMapAttributes('gallery', 'gallery', 'custom_photos'));
            self::assertSame($enabled ? ['image'] : [], $compatibility->getAttributeCompatibilityMap()['image']);
            self::assertSame([], $compatibility->getAttributeCompatibilityMap()['gallery'] ?? []);
        }
    }
}
