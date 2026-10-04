<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Config;

use Ergonode\ProductMedia\Model\Config\GalleryConfig;
use Ergonode\ProductMedia\Model\Config\ImageRulesNormalizer;
use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
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

        self::assertTrue((new GalleryConfig($scopeConfig, new Json(), new ImageRulesNormalizer()))->isSynchronizationEnabled());
    }
    public function testReadsSelectedGalleryWithoutAReservedCodeFallback(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->expects(self::once())->method('getValue')->with(GalleryConfig::XML_PATH_GALLERY_ATTRIBUTE)
            ->willReturn(' product_photos ');
        self::assertSame('product_photos', (new GalleryConfig($config, new Json(), new ImageRulesNormalizer()))->getGalleryAttributeCode());
    }

    public function testUnconfiguredGalleryRemainsEmpty(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        self::assertSame('', (new GalleryConfig($config, new Json(), new ImageRulesNormalizer()))->getGalleryAttributeCode());
    }

    #[DataProvider('unmanagedModes')]
    public function testAdditionalImageModeHasSafeDefaultAndReadsAllThreeChoices(?string $value, UnmanagedImagesMode $expected): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($value);
        self::assertSame($expected, (new GalleryConfig($config, new Json(), new ImageRulesNormalizer()))->getUnmanagedImagesMode());
    }

    public static function unmanagedModes(): array
    {
        return [[null, UnmanagedImagesMode::Keep], ['', UnmanagedImagesMode::Keep], ['keep', UnmanagedImagesMode::Keep],
            ['hide', UnmanagedImagesMode::Hide], ['remove', UnmanagedImagesMode::Remove]];
    }

    public function testInvalidExternallySetModeCannotSilentlyEnableDeletion(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('delete-everything');
        $this->expectException(LocalizedException::class);
        (new GalleryConfig($config, new Json(), new ImageRulesNormalizer()))->getUnmanagedImagesMode();
    }

}
