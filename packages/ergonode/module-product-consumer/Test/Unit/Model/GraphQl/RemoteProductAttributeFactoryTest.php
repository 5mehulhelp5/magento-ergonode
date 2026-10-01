<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\GraphQl;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Model\AttributeValueNormalizer;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductAttributeFactory;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedNumberValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringListValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class RemoteProductAttributeFactoryTest extends TestCase
{
    private RemoteProductAttributeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new RemoteProductAttributeFactory(new AttributeValueNormalizer());
    }

    public function testCreatesConsumerOwnedValuesForEachSupportedShape(): void
    {
        $text = $this->factory->fromPayload('name', ErgonodeAttributeTypeInterface::TYPE_TEXT, [[
            'language' => 'pl_PL',
            'value' => 'Krzesło',
        ]]);
        $price = $this->factory->fromPayload('price', ErgonodeAttributeTypeInterface::TYPE_PRICE, [[
            'language' => 'pl_PL',
            'value' => 12.5,
        ]]);
        $gallery = $this->factory->fromPayload('gallery', ErgonodeAttributeTypeInterface::TYPE_GALLERY, [[
            'language' => 'pl_PL',
            'value' => [['path' => '/one.jpg'], ['path' => '/two.jpg']],
        ]]);

        self::assertInstanceOf(LocalizedStringValues::class, $text->values);
        self::assertSame(['pl_PL' => 'Krzesło'], $text->values->all());
        self::assertInstanceOf(LocalizedNumberValues::class, $price->values);
        self::assertSame(['pl_PL' => 12.5], $price->values->all());
        self::assertInstanceOf(LocalizedStringListValues::class, $gallery->values);
        self::assertSame(['pl_PL' => ['/one.jpg', '/two.jpg']], $gallery->values->all());
    }

    public function testNormalizesFilePayloadToOnePathPerLanguage(): void
    {
        $file = $this->factory->fromPayload('manual', ErgonodeAttributeTypeInterface::TYPE_FILE, [[
            'language' => 'pl_PL',
            'value' => [['path' => '/manual.pdf'], ['path' => '/ignored.pdf']],
        ]]);

        self::assertInstanceOf(LocalizedStringValues::class, $file->values);
        self::assertSame(['pl_PL' => '/manual.pdf'], $file->values->all());
    }

    public function testRejectsUnsupportedValueShape(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('invalid value for product attribute');

        $this->factory->fromPayload('price', ErgonodeAttributeTypeInterface::TYPE_PRICE, [[
            'language' => 'pl_PL',
            'value' => 'not-a-number',
        ]]);
    }
}
