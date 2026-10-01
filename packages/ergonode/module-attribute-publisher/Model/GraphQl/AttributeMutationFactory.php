<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\GraphQl;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use InvalidArgumentException;

class AttributeMutationFactory
{
    private const array CREATE = [
        'date' => ['attributeCreateDate', 'AttributeCreateDateInput!'],
        'file' => ['attributeCreateFile', 'AttributeCreateFileInput!'],
        'gallery' => ['attributeCreateGallery', 'AttributeCreateGalleryInput!'],
        'image' => ['attributeCreateImage', 'AttributeCreateImageInput!'],
        'multi_select' => ['attributeCreateMultiSelect', 'AttributeCreateMultiSelectInput!'],
        'numeric' => ['attributeCreateNumeric', 'AttributeCreateNumericInput!'],
        'price' => ['attributeCreatePrice', 'AttributeCreatePriceInput!'],
        'product_relation' => ['attributeCreateProductRelation', 'AttributeCreateProductRelationInput!'],
        'select' => ['attributeCreateSelect', 'AttributeCreateSelectInput!'],
        'text' => ['attributeCreateText', 'AttributeCreateTextInput!'],
        'textarea' => ['attributeCreateTextarea', 'AttributeCreateTextareaInput!'],
        'unit' => ['attributeCreateUnit', 'AttributeCreateUnitInput!'],
    ];

    public function supports(string $type): bool
    {
        return isset(self::CREATE[$type]);
    }

    public function create(AttributeStateInterface $state): MutationOperationInterface
    {
        if (!$this->supports($state->getType())) {
            throw new InvalidArgumentException('Unsupported Ergonode attribute type: ' . $state->getType());
        }
        [$field, $inputType] = self::CREATE[$state->getType()];
        $input = [
            'code' => $state->getCode(),
            'name' => $this->translations($state->getNames()),
            'scope' => $state->getScope(),
        ];
        $parameters = $state->getParameters();
        foreach ($this->createParameterNames($state->getType()) as $name => $default) {
            if (!array_key_exists($name, $parameters) && $default === null) {
                throw new InvalidArgumentException(sprintf(
                    'Attribute parameter "%s" is required for type "%s".',
                    $name,
                    $state->getType()
                ));
            }
            $input[$name] = $parameters[$name] ?? $default;
        }
        if (in_array($state->getType(), ['select', 'multi_select'], true)) {
            $input['options'] = array_map($this->option(...), $state->getOptions());
        }

        return $this->operation($field, $inputType, $input, 'create');
    }

    public function setName(AttributeStateInterface $state): MutationOperationInterface
    {
        return $this->operation('attributeSetName', 'AttributeSetNameInput!', [
            'code' => $state->getCode(), 'name' => $this->translations($state->getNames()),
        ], 'name');
    }

    /** @param array<string, string> $metadata */
    public function addMetadata(string $code, array $metadata): MutationOperationInterface
    {
        $items = [];
        foreach ($metadata as $key => $value) {
            $items[] = ['key' => $key, 'value' => $value];
        }
        return $this->operation('attributeAddMetadata', 'AttributeAddMetadataInput!', [
            'code' => $code, 'metadata' => $items,
        ], 'metadata_add');
    }

    /** @param string[] $keys */
    public function deleteMetadata(string $code, array $keys): MutationOperationInterface
    {
        return $this->operation('attributeDeleteMetadata', 'AttributeDeleteMetadataInput!', [
            'code' => $code, 'metadataKeys' => array_values($keys),
        ], 'metadata_delete');
    }

    public function setParameter(
        string $code,
        string $type,
        string $name,
        bool|float|int|string $value
    ): MutationOperationInterface {
        $config = match ($type . ':' . $name) {
            'date:format' => ['attributeDateSetFormat', 'AttributeDateSetFormatInput!', 'format'],
            'price:currency' => ['attributePriceSetCurrency', 'AttributePriceSetCurrencyInput!', 'currency'],
            'textarea:richEdit' => ['attributeTextareaSetRichEdit', 'AttributeTextareaSetRichEditInput!', 'richEdit'],
            'unit:unitName' => ['attributeUnitSetUnit', 'AttributeUnitSetUnitInput!', 'unitName'],
            default => null,
        };
        if ($config === null) {
            throw new InvalidArgumentException(sprintf('Attribute parameter %s is not mutable for %s.', $name, $type));
        }
        return $this->operation(
            $config[0],
            $config[1],
            ['code' => $code, $config[2] => $value],
            'parameter:' . $name
        );
    }

    public function addOption(
        string $attributeCode,
        string $type,
        AttributeOptionStateInterface $option
    ): MutationOperationInterface {
        $prefix = $this->optionPrefix($type);
        return $this->operation('attribute' . $prefix . 'AddOption', 'Attribute' . $prefix . 'AddOptionInput!', [
            'code' => $attributeCode, 'option' => $this->option($option),
        ], 'option_add:' . $option->getCode());
    }

    public function renameOption(
        string $attributeCode,
        string $type,
        AttributeOptionStateInterface $option
    ): MutationOperationInterface {
        $prefix = $this->optionPrefix($type);
        return $this->operation(
            'attribute' . $prefix . 'SetOptionName',
            'Attribute' . $prefix . 'SetOptionNameInput!',
            [
                'code' => $attributeCode,
                'optionCode' => $option->getCode(),
                'optionName' => $this->translations($option->getNames()),
            ],
            'option_name:' . $option->getCode()
        );
    }

    public function deleteOption(string $attributeCode, string $type, string $optionCode): MutationOperationInterface
    {
        $prefix = $this->optionPrefix($type);
        return $this->operation('attribute' . $prefix . 'DeleteOption', 'Attribute' . $prefix . 'DeleteOptionInput!', [
            'code' => $attributeCode, 'optionCode' => $optionCode,
        ], 'option_delete:' . $optionCode);
    }

    /** @param AttributeOptionStateInterface[] $options */
    public function setOptions(string $attributeCode, string $type, array $options): MutationOperationInterface
    {
        $prefix = $this->optionPrefix($type);
        return $this->operation('attribute' . $prefix . 'SetOptions', 'Attribute' . $prefix . 'SetOptionsInput!', [
            'code' => $attributeCode, 'options' => array_map($this->option(...), $options),
        ], 'option_order');
    }

    /** @return array<string, bool|string|null> */
    private function createParameterNames(string $type): array
    {
        return match ($type) {
            'date' => ['format' => 'yyyy-MM-dd'],
            'numeric', 'text' => ['unique' => false],
            'price' => ['currency' => null],
            'textarea' => ['richEdit' => false],
            'unit' => ['unitName' => null],
            default => [],
        };
    }

    private function optionPrefix(string $type): string
    {
        return match ($type) {
            'select' => 'Select',
            'multi_select' => 'MultiSelect',
            default => throw new InvalidArgumentException('Options are supported only by select attributes.'),
        };
    }

    /** @param array<string, string> $names @return array<int, array{language: string, value: string}> */
    private function translations(array $names): array
    {
        $result = [];
        foreach ($names as $language => $value) {
            $result[] = ['language' => $language, 'value' => $value];
        }
        return $result;
    }

    /** @return array{code: string, name: array<int, array{language: string, value: string}>} */
    private function option(AttributeOptionStateInterface $option): array
    {
        return ['code' => $option->getCode(), 'name' => $this->translations($option->getNames())];
    }

    /** @param array<string, mixed> $input */
    private function operation(string $field, string $inputType, array $input, string $key): MutationOperationInterface
    {
        return new MutationOperation(
            $field,
            ['input' => new MutationVariable($inputType, $input)],
            str_ends_with($field, 'Delete') ? ['code'] : ['attribute.code'],
            ['operation_key' => $key]
        );
    }
}
