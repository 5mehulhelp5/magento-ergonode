<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMediaConsumer\Model\Media\AdditionalImageSelection;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use LogicException;
use PHPUnit\Framework\TestCase;

class AdditionalImageSelectionTest extends TestCase
{
    public function testEmptySourceIsAnIntentionalRemovalAndDuplicateLocalesReuseTheSource(): void
    {
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturn(['back' => 2]);
        $selection = new AdditionalImageSelection($rules);
        self::assertSame([], $selection->positions([$this->source([])]));
        self::assertSame(['photo.jpg' => 2], $selection->positions([
            $this->source(['pl_PL' => 'photo.jpg', 'en_GB' => 'photo.jpg']),
        ]));
    }
    public function testMissingSourceDoesNotMeanRemoval(): void
    {
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalImages')->willReturn(['back' => 2]);
        $this->expectException(LogicException::class);
        (new AdditionalImageSelection($rules))->positions([]);
    }
    /** @param array<string,string> $values */
    private function source(array $values): RemoteProductAttribute
    {
        return new RemoteProductAttribute(
            'back',
            new RemoteProductAttributeType('image'),
            new LocalizedStringValues($values)
        );
    }
}
