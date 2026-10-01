<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class SchemaContractTest extends TestCase
{
    public function testEveryReferencedMutationAndInputExistsInSnapshot(): void
    {
        $schema = json_decode((string)file_get_contents(
            dirname(__DIR__, 6)
                . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
        $operations = [
            'attributeCreateDate', 'attributeCreateFile', 'attributeCreateGallery', 'attributeCreateImage',
            'attributeCreateMultiSelect', 'attributeCreateNumeric', 'attributeCreatePrice',
            'attributeCreateProductRelation', 'attributeCreateSelect', 'attributeCreateText',
            'attributeCreateTextarea', 'attributeCreateUnit', 'attributeSetName', 'attributeAddMetadata',
            'attributeDeleteMetadata', 'attributeDateSetFormat', 'attributePriceSetCurrency',
            'attributeTextareaSetRichEdit', 'attributeUnitSetUnit', 'attributeSelectAddOption',
            'attributeSelectDeleteOption', 'attributeSelectSetOptionName', 'attributeSelectSetOptions',
            'attributeMultiSelectAddOption', 'attributeMultiSelectDeleteOption',
            'attributeMultiSelectSetOptionName', 'attributeMultiSelectSetOptions',
        ];
        foreach ($operations as $operation) {
            self::assertContains($operation, $schema['operationIndex']['mutations']);
            $input = rtrim(
                $schema['domains'][$this->domain($operation)]['mutations'][$operation]['arguments']['input']['type'],
                '!'
            );
            self::assertSame('INPUT_OBJECT', $schema['types'][$input]['kind']);
        }
    }

    private function domain(string $operation): string
    {
        return str_contains($operation, 'Option') ? 'options' : 'attributes';
    }
}
