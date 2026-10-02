<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Config;

use Ergonode\ProductMedia\Model\Config\GalleryConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class GalleryConfigTest extends TestCase
{
    public function testReadsMediaGallerySynchronizationFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('ergonode_products/media/synchronization_enabled')
            ->willReturn(true);

        self::assertTrue((new GalleryConfig($scopeConfig, new Json()))->isSynchronizationEnabled());
    }
    public function testReadsSelectedGalleryWithoutAReservedCodeFallback(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->expects(self::once())->method('getValue')->with(GalleryConfig::XML_PATH_GALLERY_ATTRIBUTE)
            ->willReturn(' product_photos ');
        self::assertSame('product_photos', (new GalleryConfig($config, new Json()))->getGalleryAttributeCode());
    }

    public function testUnconfiguredGalleryRemainsEmpty(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        self::assertSame('', (new GalleryConfig($config, new Json()))->getGalleryAttributeCode());
    }
}
