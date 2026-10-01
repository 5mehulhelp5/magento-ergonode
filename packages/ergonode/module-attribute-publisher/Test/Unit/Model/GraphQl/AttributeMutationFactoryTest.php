<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\GraphQl;

use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttributeMutationFactoryTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testBuildsTypedCreateMutation(
        string $type,
        string $field,
        string $inputType,
        array $parameters
    ): void {
        $options = in_array($type, ['select', 'multi_select'], true)
            ? [new AttributeOptionState('red', ['pl_PL' => 'Czerwony'])] : [];
        $operation = (new AttributeMutationFactory())->create(new AttributeState(
            'color',
            $type,
            'LOCAL',
            ['pl_PL' => 'Kolor'],
            $parameters,
            [],
            $options
        ));

        self::assertSame($field, $operation->getField());
        self::assertSame($inputType, $operation->getVariables()['input']->getType());
        self::assertSame('color', $operation->getVariables()['input']->getValue()['code']);
    }

    public static function typeProvider(): array
    {
        return [
            'date' => ['date', 'attributeCreateDate', 'AttributeCreateDateInput!', ['format' => 'yyyy-MM-dd']],
            'file' => ['file', 'attributeCreateFile', 'AttributeCreateFileInput!', []],
            'gallery' => ['gallery', 'attributeCreateGallery', 'AttributeCreateGalleryInput!', []],
            'image' => ['image', 'attributeCreateImage', 'AttributeCreateImageInput!', []],
            'multi select' => ['multi_select', 'attributeCreateMultiSelect', 'AttributeCreateMultiSelectInput!', []],
            'numeric' => ['numeric', 'attributeCreateNumeric', 'AttributeCreateNumericInput!', ['unique' => false]],
            'price' => ['price', 'attributeCreatePrice', 'AttributeCreatePriceInput!', ['currency' => 'PLN']],
            'product relation' => [
                'product_relation',
                'attributeCreateProductRelation',
                'AttributeCreateProductRelationInput!',
                [],
            ],
            'select' => ['select', 'attributeCreateSelect', 'AttributeCreateSelectInput!', []],
            'text' => ['text', 'attributeCreateText', 'AttributeCreateTextInput!', ['unique' => false]],
            'textarea' => [
                'textarea',
                'attributeCreateTextarea',
                'AttributeCreateTextareaInput!',
                ['richEdit' => false],
            ],
            'unit' => ['unit', 'attributeCreateUnit', 'AttributeCreateUnitInput!', ['unitName' => 'PIECE']],
        ];
    }
}
