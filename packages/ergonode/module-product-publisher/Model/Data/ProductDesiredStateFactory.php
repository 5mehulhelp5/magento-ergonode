<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\ProductDesiredStateFactoryInterface;

class ProductDesiredStateFactory implements ProductDesiredStateFactoryInterface
{
    public function createValue(
        string $attributeCode,
        string $type,
        array $translations,
        ?string $twoWayRelation = null,
        array $clearedLanguageCodes = []
    ): ProductAttributeValueInterface {
        return new ProductAttributeValue(
            $attributeCode,
            $type,
            $translations,
            $twoWayRelation,
            $clearedLanguageCodes
        );
    }

    public function createRelations(
        array $bindingCodes = [],
        array $variantSkus = [],
        array $groupedChildren = []
    ): ProductRelationStateInterface {
        return new ProductRelationState($bindingCodes, $variantSkus, $groupedChildren);
    }

    public function createProduct(
        string $sku,
        string $type,
        string $templateCode,
        array $statuses,
        array $values,
        ProductRelationStateInterface $relations,
        bool $deleted = false,
        array $authoritativeCollections = [],
        ?int $magentoProductId = null,
        ?string $ergonodeSku = null,
        string $identityMode = ProductIdentityInterface::MODE_SHARED
    ): ProductStateInterface {
        return new ProductState(
            $sku,
            $type,
            $templateCode,
            $statuses,
            $values,
            $relations,
            $deleted,
            $authoritativeCollections,
            $magentoProductId,
            $ergonodeSku,
            $identityMode
        );
    }
}
