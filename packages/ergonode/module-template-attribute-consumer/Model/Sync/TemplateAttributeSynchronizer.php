<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;

class TemplateAttributeSynchronizer
{
    public function __construct(
        private readonly TemplateStructureResource $resource,
        private readonly TemplateStructureOwnershipResource $ownershipResource,
        private readonly AttributeSetManager $attributeSetManager,
        private readonly ChangeReport $changeReport,
        private readonly ManualPlacementResource $manualPlacement
    ) {
    }

    /**
     * @param array<int, array{entity_attribute_id: int, attribute_group_id: int, sort_order: int}> $existingRows
     * @param array<string, int> $stats
     */
    public function sync(
        int $attributeSetId,
        int $attributeGroupId,
        int $attributeId,
        string $templateCode,
        string $sectionCode,
        string $ergonodeAttributeCode,
        string $magentoAttributeCode,
        int $sortOrder,
        array &$existingRows,
        array &$stats
    ): void {
        if ($this->manualPlacement->isManual($attributeSetId, $attributeId)) {
            $stats['attributes_skipped'] = ($stats['attributes_skipped'] ?? 0) + 1;
            return;
        }
        $existing = $existingRows[$attributeId] ?? null;

        if ($existing === null) {
            $entityAttributeId = $this->resource->insertEntityAttribute(
                $this->attributeSetManager->getProductEntityTypeId(),
                $attributeSetId,
                $attributeGroupId,
                $attributeId,
                $sortOrder
            );
            $existingRows[$attributeId] = [
                'entity_attribute_id' => $entityAttributeId,
                'attribute_group_id' => $attributeGroupId,
                'sort_order' => $sortOrder,
            ];
            $this->ownershipResource->saveAttributeOwnership(
                $templateCode,
                $attributeSetId,
                $ergonodeAttributeCode,
                $attributeId,
                $attributeGroupId,
                $entityAttributeId
            );
            $stats['attributes_inserted']++;
            $this->changeReport->add(
                'template_attribute',
                $templateCode . '::' . $sectionCode . '::' . $ergonodeAttributeCode,
                ChangeReport::ACTION_INSERTED,
                'Inserted Magento entity attribute into template group.',
                [
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'ergonode_attribute_code' => $ergonodeAttributeCode,
                    'magento_attribute_code' => $magentoAttributeCode,
                    'magento_attribute_id' => $attributeId,
                    'sort_order' => $sortOrder,
                ]
            );

            return;
        }

        $data = [];
        if ((int)$existing['attribute_group_id'] !== $attributeGroupId) {
            $data['attribute_group_id'] = $attributeGroupId;
        }
        if ((int)$existing['sort_order'] !== $sortOrder) {
            $data['sort_order'] = $sortOrder;
        }

        if ($data === []) {
            $this->ownershipResource->saveAttributeOwnership(
                $templateCode,
                $attributeSetId,
                $ergonodeAttributeCode,
                $attributeId,
                $attributeGroupId,
                (int)$existing['entity_attribute_id']
            );
            $stats['attributes_unchanged']++;
            $this->changeReport->add(
                'template_attribute',
                $templateCode . '::' . $sectionCode . '::' . $ergonodeAttributeCode,
                ChangeReport::ACTION_UNCHANGED,
                'Magento entity attribute placement is unchanged.',
                [
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'ergonode_attribute_code' => $ergonodeAttributeCode,
                    'magento_attribute_code' => $magentoAttributeCode,
                    'magento_attribute_id' => $attributeId,
                    'sort_order' => $sortOrder,
                ]
            );

            return;
        }

        $entityAttributeId = (int)$existing['entity_attribute_id'];
        $this->resource->updateEntityAttribute($entityAttributeId, $data);
        $existingRows[$attributeId] = [
            'entity_attribute_id' => $entityAttributeId,
            'attribute_group_id' => (int)($data['attribute_group_id'] ?? $existing['attribute_group_id']),
            'sort_order' => (int)($data['sort_order'] ?? $existing['sort_order']),
        ];
        $this->ownershipResource->saveAttributeOwnership(
            $templateCode,
            $attributeSetId,
            $ergonodeAttributeCode,
            $attributeId,
            $attributeGroupId,
            $entityAttributeId
        );
        $stats['attributes_moved']++;
        $this->changeReport->add(
            'template_attribute',
            $templateCode . '::' . $sectionCode . '::' . $ergonodeAttributeCode,
            ChangeReport::ACTION_UPDATED,
            'Updated Magento entity attribute placement.',
            [
                'attribute_set_id' => $attributeSetId,
                'entity_attribute_id' => $entityAttributeId,
                'ergonode_attribute_code' => $ergonodeAttributeCode,
                'magento_attribute_code' => $magentoAttributeCode,
                'magento_attribute_id' => $attributeId,
                'changes' => $data,
            ]
        );
    }
}
