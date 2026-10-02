<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\ProductMediaConsumer\Model\Media\AdditionalImageSelection;
use Ergonode\ProductMedia\Model\Gallery\AdditionalImages;

use LogicException;
use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use PHPUnit\Framework\TestCase;

class ProductGallerySelectionProviderTest extends TestCase
{
    public function testReturnsNullWhenSelectedGalleryAttributeIsAbsent(): void
    {
        self::assertNull(($this->provider())->provide([
            new RemoteProductAttribute(
                'color',
                new RemoteProductAttributeType('select'),
                new LocalizedStringValues(['pl_PL' => 'blue'])
            ),
        ]));
    }

    public function testPreservesPresentEmptyGallerySelection(): void
    {
        $selection = ($this->provider())->provide([$this->gallery([])]);

        self::assertNotNull($selection);
        self::assertSame([], $selection->paths());
    }

    public function testFlattensLocalizedGalleryPathsWithoutDuplicates(): void
    {
        $selection = ($this->provider())->provide([$this->gallery([
            'pl_PL' => ['/one.jpg', '/two.jpg'],
            'en_GB' => ['/one.jpg'],
        ])]);

        self::assertNotNull($selection);
        self::assertSame(['/one.jpg', '/two.jpg'], $selection->paths());
    }

    public function testUnselectedGalleryIsIgnored(): void
    {
        $attribute = new RemoteProductAttribute(
            'gallery',
            new RemoteProductAttributeType('gallery'),
            new LocalizedStringListValues(['pl_PL' => ['/ignored.jpg']])
        );
        self::assertNull($this->provider()->provide([$attribute]));
    }

    public function testRejectsASelectedAttributeWhoseRemoteTypeChanged(): void
    {
        $attribute = new RemoteProductAttribute(
            'product_photos',
            new RemoteProductAttributeType('image'),
            new LocalizedStringValues(['pl_PL' => '/photo.jpg'])
        );
        $this->expectException(LogicException::class);
        $this->provider()->provide([$attribute]);
    }

    private function provider(): ProductGallerySelectionProvider
    {
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $configuration->method('getGalleryAttributeCode')->willReturn('product_photos');

        return new ProductGallerySelectionProvider(
            $configuration,
            $this->createStub(AdditionalImageSelection::class),
            new AdditionalImages()
        );
    }

    /** @param array<string, list<string>> $values */
    private function gallery(array $values): RemoteProductAttribute
    {
        return new RemoteProductAttribute(
            'product_photos',
            new RemoteProductAttributeType('gallery'),
            new LocalizedStringListValues($values)
        );
    }
}
