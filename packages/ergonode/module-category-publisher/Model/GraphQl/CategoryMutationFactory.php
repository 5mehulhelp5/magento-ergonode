<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\GraphQl;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;

class CategoryMutationFactory
{
    public function create(CategoryStateInterface $state): MutationOperationInterface
    {
        return $this->operation('categoryCreate', 'CategoryCreateInput!', [
            'code' => $state->getCode(), 'name' => $this->translations($state->getNames()),
        ], 'create', ['category.code', 'category.name.language', 'category.name.value']);
    }

    public function setName(CategoryStateInterface $state): MutationOperationInterface
    {
        return $this->operation('categorySetName', 'CategorySetNameInput!', [
            'code' => $state->getCode(), 'name' => $this->translations($state->getNames()),
        ], 'name', ['category.code']);
    }

    public function delete(string $code): MutationOperationInterface
    {
        return $this->operation('categoryDelete', 'CategoryDeleteInput!', ['code' => $code], 'delete', ['code']);
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
