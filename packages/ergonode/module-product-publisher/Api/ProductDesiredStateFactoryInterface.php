<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductAttributeValueInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;

interface ProductDesiredStateFactoryInterface
{
    /**
     * @param string $attributeCode
     * @param string $type
     * @param array<string, float|int|string|string[]> $translations
     * @param string|null $twoWayRelation
     * @param string[] $clearedLanguageCodes
     * @return ProductAttributeValueInterface
     */
    public function createValue(
        string $attributeCode,
        string $type,
        array $translations,
        ?string $twoWayRelation = null,
        array $clearedLanguageCodes = []
    ): ProductAttributeValueInterface;

    /**
     * @param string[] $bindingCodes
     * @param string[] $variantSkus
     * @param array<string, int> $groupedChildren
     * @return ProductRelationStateInterface
     */
    public function createRelations(
        array $bindingCodes = [],
        array $variantSkus = [],
        array $groupedChildren = []
    ): ProductRelationStateInterface;

    /**
     * @param string $sku
     * @param string $type
     * @param string $templateCode
     * @param array<string, string> $statuses
     * @param ProductAttributeValueInterface[] $values
     * @param ProductRelationStateInterface $relations
     * @param bool $deleted
     * @param array<string, bool> $authoritativeCollections
     * @param int|null $magentoProductId
     * @param string|null $ergonodeSku
     * @param string $identityMode
     * @return ProductStateInterface
     */
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
    ): ProductStateInterface;
}
