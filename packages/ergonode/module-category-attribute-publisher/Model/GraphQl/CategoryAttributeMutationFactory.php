<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\GraphQl;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeValueInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use InvalidArgumentException;

class CategoryAttributeMutationFactory
{
    public function addAllowedAttribute(string $attributeCode): MutationOperationInterface
    {
        return $this->operation(
            'categoryAttributeAddAttribute',
            'CategoryAttributeAddAttributeInput!',
            ['attributeCode' => $attributeCode],
            'allowed_add:' . $attributeCode,
            ['__typename'],
            'category_attribute:' . $attributeCode
        );
    }

    public function setValue(string $categoryCode, CategoryAttributeValueInterface $value): MutationOperationInterface
    {
        $suffix = ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES[$value->getType()] ?? null;
        if ($suffix === null) {
            throw new InvalidArgumentException('Unsupported category attribute value type: ' . $value->getType());
        }

        return $this->operation(
            'categoryAddAttributeValueTranslations' . $suffix,
            'CategoryAddAttributeValueTranslations' . $suffix . 'Input!',
            [
                'attributeCode' => $value->getAttributeCode(),
                'categoryCode' => $categoryCode,
                'translations' => $this->translations($value->getTranslations()),
            ],
            'value:' . $value->getAttributeCode(),
            ['category.code']
        );
    }

    /** @param string[] $languages */
    public function deleteValueTranslations(
        string $categoryCode,
        string $attributeCode,
        array $languages
    ): MutationOperationInterface {
        return $this->operation(
            'categoryDeleteAttributeValueTranslations',
            'CategoryDeleteAttributeValueTranslationsInput!',
            [
                'attributeCode' => $attributeCode,
                'code' => $categoryCode,
                'languages' => array_values($languages),
            ],
            'value_delete:' . $attributeCode,
            ['category.code']
        );
    }

    /** @param array<string, mixed> $values @return array<int, array{language: string, value: mixed}> */
    private function translations(array $values): array
    {
        $result = [];
        foreach ($values as $language => $value) {
            $result[] = ['language' => $language, 'value' => $value];
        }

        return $result;
    }

    /** @param array<string, mixed> $input @param string[] $responseFields */
    private function operation(
        string $field,
        string $inputType,
        array $input,
        string $key,
        array $responseFields,
        ?string $globalOperationKey = null
    ): MutationOperationInterface {
        return new MutationOperation(
            $field,
            ['input' => new MutationVariable($inputType, $input)],
            $responseFields,
            array_filter([
                'operation_key' => $key,
                'global_operation_key' => $globalOperationKey,
            ], static fn (mixed $value): bool => $value !== null)
        );
    }
}
