<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Test\Unit\Model\GraphQl;

use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeValue;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\CategoryAttributeMutationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryAttributeMutationFactoryTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testBuildsEveryTypedValueMutation(string $type, string $suffix, mixed $value): void
    {
        $operation = (new CategoryAttributeMutationFactory())->setValue(
            'chairs',
            new CategoryAttributeValue('description', $type, ['pl_PL' => $value])
        );
        $input = $operation->getVariables()['input'];

        self::assertSame('categoryAddAttributeValueTranslations' . $suffix, $operation->getField());
        self::assertSame('CategoryAddAttributeValueTranslations' . $suffix . 'Input!', $input->getType());
        self::assertSame('chairs', $input->getValue()['categoryCode']);
    }

    public static function typeProvider(): array
    {
        return [
            'date' => ['date', 'Date', '2026-08-07'],
            'file' => ['file', 'File', ['/manual.pdf']],
            'gallery' => ['gallery', 'Gallery', ['/one.jpg', '/two.jpg']],
            'image' => ['image', 'Image', '/chair.jpg'],
            'multi select' => ['multi_select', 'MultiSelect', ['red', 'blue']],
            'numeric' => ['numeric', 'Numeric', 12.5],
            'price' => ['price', 'Price', 99.9],
            'product relation' => ['product_relation', 'ProductRelation', ['SKU-1']],
            'select' => ['select', 'Select', 'red'],
            'text' => ['text', 'Text', 'Chair'],
            'textarea' => ['textarea', 'Textarea', 'Long copy'],
            'unit' => ['unit', 'Unit', 4.0],
        ];
    }
}
