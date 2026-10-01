<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;

class TemplateStructureCleanupPlanProvider
{
    public function __construct(
        private readonly TemplateStructureResource $resource,
        private readonly TemplateStructureOwnershipResource $ownershipResource,
        private readonly ProductAttributePlacementPolicyInterface $productAttributePolicy,
        private readonly ManualPlacementResource $manualPlacement
    ) {
    }

    /**
     * @param int[] $expectedGroupIds
     * @param int[] $expectedAttributeIds
     * @param array<int, int> $projectedAttributeMoveCountByGroup
     * @return array{
     *     attributes: array<int, array<string, int|string|bool>>,
     *     groups: array<int, array<string, int|string>>
     * }
     */
    public function getPlan(
        string $templateCode,
        int $currentAttributeSetId,
        array $expectedGroupIds,
        array $expectedAttributeIds,
        array $projectedAttributeMoveCountByGroup = []
    ): array {
        $attributes = [];
        $groups = [];
        foreach ($this->ownershipResource->loadAttributeSetIds($templateCode) as $attributeSetId) {
            $isCurrentSet = $attributeSetId === $currentAttributeSetId;
            $expectedAttributeMap = $isCurrentSet
                ? array_fill_keys(array_map('intval', $expectedAttributeIds), true)
                : [];
            $expectedGroupMap = $isCurrentSet
                ? array_fill_keys(array_map('intval', $expectedGroupIds), true)
                : [];
            $ownedRemovalCountByGroup = [];

            foreach ($this->ownershipResource->loadAttributeOwnerships($templateCode, $attributeSetId) as $ownership) {
                if (isset($expectedAttributeMap[$ownership['magento_attribute_id']])) {
                    continue;
                }

                $placementExists = $this->resource->entityAttributeExists($ownership['entity_attribute_id']);
                $protected = $this->manualPlacement->isManual($attributeSetId, $ownership['magento_attribute_id'])
                    || !$ownership['is_user_defined']
                    || !empty($ownership['is_required'])
                    || $this->productAttributePolicy->isProtected(
                        $ownership['magento_attribute_code']
                    );
                $attributes[] = $ownership + [
                    'attribute_set_id' => $attributeSetId,
                    'placement_exists' => $placementExists,
                    'protected' => $protected,
                ];
                if ($placementExists && !$protected) {
                    $groupId = $ownership['attribute_group_id'];
                    $ownedRemovalCountByGroup[$groupId] = ($ownedRemovalCountByGroup[$groupId] ?? 0) + 1;
                }
            }

            foreach ($this->ownershipResource->loadGroupOwnerships($templateCode, $attributeSetId) as $ownership) {
                $groupId = $ownership['attribute_group_id'];
                if (isset($expectedGroupMap[$groupId])) {
                    continue;
                }

                $remainingAttributes = max(
                    0,
                    $this->resource->countGroupAttributes($attributeSetId, $groupId)
                    - ($ownedRemovalCountByGroup[$groupId] ?? 0)
                    - ($projectedAttributeMoveCountByGroup[$groupId] ?? 0)
                );
                $groups[] = $ownership + [
                    'attribute_set_id' => $attributeSetId,
                    'remaining_attributes' => $remainingAttributes,
                ];
            }
        }

        return ['attributes' => $attributes, 'groups' => $groups];
    }
}
