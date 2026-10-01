<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttribute\Api\StructureMetadataContributorInterface;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;

class StructureMetadataContributor implements StructureMetadataContributorInterface
{
    public function __construct(
        private readonly ManualPlacementResource $manualPlacement,
        private readonly TemplateStructureOwnershipResource $ownership,
        private readonly ProductAttributePlacementPolicyInterface $placementPolicy
    ) {
    }

    public function contribute(string $templateCode, int $attributeSetId, array $structure): array
    {
        $groups = array_column(
            $this->ownership->loadGroupOwnerships($templateCode, $attributeSetId),
            null,
            'attribute_group_id'
        );
        foreach ($structure['groups'] as &$group) {
            $owner = $groups[(int)$group['attribute_group_id']] ?? null;
            $group['managed_by_ergonode'] = $owner !== null;
            if ($owner !== null && $group['ergonode_section_codes'] === []) {
                $group['ergonode_section_codes'] = [$owner['section_code']];
            }
        }
        unset($group);
        $manualIds = $this->manualPlacement->getAttributeIds($attributeSetId);
        $sourceCodes = array_column($structure['sourceAttributes'], 'attribute_code');
        foreach ($structure['attributes'] as &$attribute) {
            $attribute['manual_placement'] = in_array((int)$attribute['attribute_id'], $manualIds, true);
            $attribute['placement_protected'] = !(bool)$attribute['is_user_defined']
                || $this->placementPolicy->isProtected($attribute['attribute_code']);
            $attribute['missing_from_template'] = $attribute['ergonode_attribute_codes'] !== []
                && array_intersect($attribute['ergonode_attribute_codes'], $sourceCodes) === [];
        }
        unset($attribute);

        return $structure;
    }
}
