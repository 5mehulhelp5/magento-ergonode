<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

class TemplateMappedGroupResolver
{
    public function __construct(
        private readonly TemplateStructureResource $resource,
        private readonly TemplateStructureOwnershipResource $ownershipResource
    ) {
    }

    /**
     * @param array<int, true> $claimedAttributeGroupIds
     * @return array{group: array<string, mixed>}|null
     */
    public function resolve(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        ?int $mappedAttributeGroupId,
        array $claimedAttributeGroupIds
    ): ?array {
        if ($mappedAttributeGroupId === null || isset($claimedAttributeGroupIds[$mappedAttributeGroupId])) {
            return null;
        }

        $group = $this->resource->findAttributeGroupById($attributeSetId, $mappedAttributeGroupId);
        if ($group === null) {
            return null;
        }

        $owner = $this->ownershipResource->findGroupOwnerById($mappedAttributeGroupId);
        if ($owner === null
            || ($owner['template_code'] !== $templateCode
                || $owner['attribute_set_id'] !== $attributeSetId
                || $owner['section_code'] !== $sectionCode)
        ) {
            return null;
        }

        return [
            'group' => $group,
        ];
    }
}
