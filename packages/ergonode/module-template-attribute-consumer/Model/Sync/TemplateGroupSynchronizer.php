<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;

class TemplateGroupSynchronizer
{
    public function __construct(
        private readonly TemplateStructureResource $resource,
        private readonly TemplateStructureOwnershipResource $ownershipResource,
        private readonly TemplateMappedGroupResolver $mappedGroupResolver,
        private readonly TemplateGroupCodeResolver $groupCodeResolver,
        private readonly TemplateStructureNamer $namer,
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly ChangeReport $changeReport
    ) {
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     is_synthetic: bool,
     *     attribute_group_id: int|null,
     *     sort_order: int
     * } $section
     * @param array<string, int> $stats
     * @param array<int, true> $claimedAttributeGroupIds
     */
    public function ensure(
        string $templateCode,
        int $attributeSetId,
        array $section,
        array &$stats,
        array &$claimedAttributeGroupIds
    ): ?int {
        $sectionCode = (string)$section['code'];
        $groupName = $this->namer->buildGroupName(
            $sectionCode,
            (string)$section['name'],
            (bool)$section['is_synthetic']
        );
        $sortOrder = max(1, (int)$section['sort_order']);
        $mappedGroup = $this->mappedGroupResolver->resolve(
            $templateCode,
            $attributeSetId,
            $sectionCode,
            !empty($section['attribute_group_id']) ? (int)$section['attribute_group_id'] : null,
            $claimedAttributeGroupIds
        );
        $group = $mappedGroup['group'] ?? null;

        $groupCode = $this->groupCodeResolver->resolve(
            $templateCode,
            $attributeSetId,
            $sectionCode,
            $group !== null ? (int)$group['attribute_group_id'] : null
        );

        if ($group === null) {
            $group = $this->resource->findAttributeGroupByCode($attributeSetId, $groupCode);
        }

        if ($group === null) {
            $attributeGroupId = $this->createGroup(
                $templateCode,
                $attributeSetId,
                $sectionCode,
                $groupCode,
                $groupName,
                $sortOrder,
                $stats
            );
            $claimedAttributeGroupIds[$attributeGroupId] = true;

            return $attributeGroupId;
        }

        $attributeGroupId = (int)$group['attribute_group_id'];
        $this->updateGroup(
            $templateCode,
            $attributeSetId,
            $sectionCode,
            $attributeGroupId,
            $groupCode,
            $groupName,
            $sortOrder,
            $group,
            $stats
        );
        if ((int)($section['attribute_group_id'] ?? 0) !== $attributeGroupId) {
            $this->templateCacheProvider->saveSectionGroupId($templateCode, $sectionCode, $attributeGroupId);
        }
        $claimedAttributeGroupIds[$attributeGroupId] = true;

        return $attributeGroupId;
    }

    /**
     * @param array<string, int> $stats
     */
    private function createGroup(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        string $groupCode,
        string $groupName,
        int $sortOrder,
        array &$stats
    ): int {
        $attributeGroupId = $this->resource->insertAttributeGroup([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_name' => $groupName,
            'sort_order' => $sortOrder,
            'attribute_group_code' => $groupCode,
            'tab_group_code' => $groupName,
        ]);
        $this->ownershipResource->saveGroupOwnership(
            $templateCode,
            $attributeSetId,
            $sectionCode,
            $attributeGroupId
        );
        $this->templateCacheProvider->saveSectionGroupId($templateCode, $sectionCode, $attributeGroupId);
        $stats['groups_created']++;
        $this->changeReport->add(
            'template_group',
            $templateCode . '::' . $sectionCode,
            ChangeReport::ACTION_INSERTED,
            'Created Magento attribute group for Ergonode section.',
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'attribute_group_code' => $groupCode,
                'attribute_group_name' => $groupName,
                'sort_order' => $sortOrder,
            ]
        );

        return $attributeGroupId;
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, int> $stats
     */
    private function updateGroup(
        string $templateCode,
        int $attributeSetId,
        string $sectionCode,
        int $attributeGroupId,
        string $groupCode,
        string $groupName,
        int $sortOrder,
        array $group,
        array &$stats
    ): void {
        $data = [];
        if ((string)$group['attribute_group_code'] !== $groupCode) {
            $data['attribute_group_code'] = $groupCode;
        }
        if ((string)$group['attribute_group_name'] !== $groupName) {
            $data['attribute_group_name'] = $groupName;
            $data['tab_group_code'] = $groupName;
        }
        if ((int)$group['sort_order'] !== $sortOrder) {
            $data['sort_order'] = $sortOrder;
        }

        if ($data !== []) {
            $this->resource->updateAttributeGroup($attributeGroupId, $data);
            $stats['groups_updated']++;
            $this->changeReport->add(
                'template_group',
                $templateCode . '::' . $sectionCode,
                ChangeReport::ACTION_UPDATED,
                'Updated Magento attribute group for Ergonode section.',
                [
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'changes' => $data,
                ]
            );

            return;
        }

        $this->changeReport->add(
            'template_group',
            $templateCode . '::' . $sectionCode,
            ChangeReport::ACTION_UNCHANGED,
            'Magento attribute group is unchanged.',
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'attribute_group_code' => $groupCode,
            ]
        );
    }
}
