<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\GraphQl;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;
use InvalidArgumentException;

class ProductMutationFactory
{
    private const array CREATE_FIELDS = [
        ProductStateInterface::TYPE_SIMPLE => 'productCreateSimple',
        ProductStateInterface::TYPE_VARIABLE => 'productCreateVariable',
        ProductStateInterface::TYPE_GROUPING => 'productCreateGrouping',
    ];

    public function create(ProductStateInterface $state): MutationOperationInterface
    {
        $field = self::CREATE_FIELDS[$state->getType()] ?? null;
        if ($field === null) {
            throw new InvalidArgumentException('Unsupported product create type: ' . $state->getType());
        }

        return $this->operation($field, ucfirst($field) . 'Input!', [
            'sku' => $state->getSku(),
            'templateCode' => $state->getTemplateCode(),
        ], $state->getSku(), 'create', ['product.sku']);
    }

    /**
     * Create a product while explicitly allowing Ergonode to assign its native SKU.
     */
    public function createWithAssignedSku(ProductStateInterface $state): MutationOperationInterface
    {
        $field = self::CREATE_FIELDS[$state->getType()] ?? null;
        if ($field === null) {
            throw new InvalidArgumentException('Unsupported product create type: ' . $state->getType());
        }

        return $this->operation($field, ucfirst($field) . 'Input!', [
            'templateCode' => $state->getTemplateCode(),
        ], $state->getSku(), 'identity:create', ['product.sku'], productId: $state->getMagentoProductId());
    }

    public function delete(string $sku): MutationOperationInterface
    {
        return $this->operation('productDelete', 'ProductDeleteInput!', ['sku' => $sku], $sku, 'delete', ['sku']);
    }

    public function setTemplate(string $sku, string $templateCode): MutationOperationInterface
    {
        return $this->operation('productSetTemplate', 'ProductSetTemplateInput!', [
            'sku' => $sku, 'template' => $templateCode,
        ], $sku, 'template', ['product.sku']);
    }

    public function setStatus(string $sku, string $language, string $statusCode): MutationOperationInterface
    {
        return $this->operation('productSetStatus', 'ProductSetStatusInput!', [
            'sku' => $sku, 'language' => $language, 'statusCode' => $statusCode,
        ], $sku, 'status:' . $language, ['product.sku'], $language);
    }

    public function setValue(
        string $sku,
        ProductAttributeValueInterface $value,
        string $language
    ): MutationOperationInterface {
        $suffix = ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES[$value->getType()] ?? null;
        if ($suffix === null || !array_key_exists($language, $value->getTranslations())) {
            throw new InvalidArgumentException('Unsupported or missing product attribute translation.');
        }
        $input = [
            'sku' => $sku,
            'attributeCode' => $value->getAttributeCode(),
            'translations' => [[
                'language' => $language,
                'value' => $value->getTranslations()[$language],
            ]],
        ];
        if ($value->getType() === ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION
            && $value->getTwoWayRelation() !== null
        ) {
            $input['twoWayRelation'] = $value->getTwoWayRelation();
        }

        return $this->operation(
            'productAddAttributeValueTranslations' . $suffix,
            'ProductAddAttributeValueTranslations' . $suffix . 'Input!',
            $input,
            $sku,
            'value:' . $value->getAttributeCode() . ':' . $language,
            ['product.sku'],
            $language,
            $value->getAttributeCode()
        );
    }

    public function deleteValueTranslation(
        string $sku,
        string $attributeCode,
        string $language
    ): MutationOperationInterface {
        return $this->operation(
            'productDeleteAttributeValueTranslations',
            'ProductDeleteAttributeValueTranslationsInput!',
            ['sku' => $sku, 'attributeCode' => $attributeCode, 'languages' => [$language]],
            $sku,
            'value_delete:' . $attributeCode . ':' . $language,
            ['product.sku'],
            $language,
            $attributeCode
        );
    }

    /** @param string[] $bindingCodes */
    public function setBindings(string $sku, array $bindingCodes): MutationOperationInterface
    {
        return $this->operation('productVariableSetBindings', 'ProductVariableSetBindingsInput!', [
            'sku' => $sku, 'bindingCodes' => array_values($bindingCodes),
        ], $sku, 'bindings', ['product.sku']);
    }

    public function addVariant(string $sku, string $variantSku): MutationOperationInterface
    {
        return $this->operation('productVariableAddVariant', 'ProductVariableAddVariantInput!', [
            'sku' => $sku, 'variantSku' => $variantSku,
        ], $sku, 'variant:add:' . $variantSku, ['product.sku'], relatedSku: $variantSku);
    }

    public function removeVariant(string $sku, string $variantSku): MutationOperationInterface
    {
        return $this->operation('productVariableRemoveVariant', 'ProductVariableRemoveVariantInput!', [
            'sku' => $sku, 'variantSku' => $variantSku,
        ], $sku, 'variant:remove:' . $variantSku, ['product.sku'], relatedSku: $variantSku);
    }

    public function addGroupedChild(string $sku, string $childSku, int $quantity): MutationOperationInterface
    {
        return $this->operation('productGroupingAddChild', 'ProductGroupingAddChildInput!', [
            'sku' => $sku, 'childSku' => $childSku, 'quantity' => $quantity,
        ], $sku, 'child:add:' . $childSku, ['product.sku'], relatedSku: $childSku);
    }

    public function setGroupedChildQuantity(string $sku, string $childSku, int $quantity): MutationOperationInterface
    {
        return $this->operation('productGroupingSetChildQuantity', 'ProductGroupingSetChildQuantityInput!', [
            'sku' => $sku, 'childSku' => $childSku, 'quantity' => $quantity,
        ], $sku, 'child:quantity:' . $childSku, ['product.sku'], relatedSku: $childSku);
    }

    public function removeGroupedChild(string $sku, string $childSku): MutationOperationInterface
    {
        return $this->operation('productGroupingRemoveChild', 'ProductGroupingRemoveChildInput!', [
            'sku' => $sku, 'childSku' => $childSku,
        ], $sku, 'child:remove:' . $childSku, ['product.sku'], relatedSku: $childSku);
    }

    /**
     * @param array<string, mixed> $input
     * @param string[] $responseFields
     */
    private function operation(
        string $field,
        string $inputType,
        array $input,
        string $sku,
        string $key,
        array $responseFields,
        ?string $language = null,
        ?string $attributeCode = null,
        ?string $relatedSku = null,
        ?int $productId = null
    ): MutationOperationInterface {
        return new MutationOperation(
            $field,
            ['input' => new MutationVariable($inputType, $input)],
            $responseFields,
            array_filter([
                'operation_key' => $key,
                'entity_sku' => $sku,
                'attribute_code' => $attributeCode,
                'language' => $language,
                'related_sku' => $relatedSku,
                'magento_product_id' => $productId,
            ], static fn (mixed $value): bool => $value !== null)
        );
    }
}
