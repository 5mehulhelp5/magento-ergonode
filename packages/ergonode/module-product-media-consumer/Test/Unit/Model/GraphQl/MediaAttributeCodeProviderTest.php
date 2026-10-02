<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\GraphQl;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMediaConsumer\Model\GraphQl\MediaAttributeCodeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaAttributeCodeProviderTest extends TestCase
{
    /** @return array<string, array{bool, string[]}> */
    public static function configurationProvider(): array
    {
        return [
            'enabled' => [true, ['product_photos']],
            'disabled' => [false, []],
        ];
    }

    /** @param string[] $expected */
    #[DataProvider('configurationProvider')]
    public function testContributesGalleryCodeOnlyWhenEnabled(bool $enabled, array $expected): void
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn($enabled);
        $configuration->method('getGalleryAttributeCode')->willReturn('product_photos');

        self::assertSame($expected, (new MediaAttributeCodeProvider($configuration, $this
            ->createStub(GalleryRulesInterface::class)))->getAttributeCodes());
    }
}
