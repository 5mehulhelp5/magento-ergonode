<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\GraphQl;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;

class ProductCategoryMutationBuilder
{
    /** @param string[] $categoryCodes */
    public function add(string $sku, array $categoryCodes): MutationOperationInterface
    {
        return $this->operation('productAddCategories', 'ProductAddCategoriesInput!', [
            'sku' => $sku,
            'categoryCodes' => array_values($categoryCodes),
        ], $sku, 'categories:add');
    }

    /** @param string[] $categoryCodes */
    public function remove(string $sku, array $categoryCodes): MutationOperationInterface
    {
        return $this->operation('productRemoveCategories', 'ProductRemoveCategoriesInput!', [
            'sku' => $sku,
            'categoryCodes' => array_values($categoryCodes),
        ], $sku, 'categories:remove');
    }

    /** @param array<string, mixed> $input */
    private function operation(
        string $field,
        string $inputType,
        array $input,
        string $sku,
        string $key
    ): MutationOperationInterface {
        return new MutationOperation(
            $field,
            ['input' => new MutationVariable($inputType, $input)],
            ['product.sku'],
            ['operation_key' => $key, 'entity_sku' => $sku]
        );
    }
}
