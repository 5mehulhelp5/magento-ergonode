<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Throwable;

class TemplateStructureCleaner
{
    public function __construct(
        private readonly TemplateStructureResource $resource,
        private readonly TemplateStructureOwnershipResource $ownershipResource,
        private readonly TemplateStructureCleanupPlanProvider $planProvider,
        private readonly ChangeReport $changeReport
    ) {
    }

    public function removeTemplate(string $templateCode): void
    {
        $stats = ['attributes_removed' => 0, 'groups_deleted' => 0];
        $this->resource->beginTransaction();
        try {
            $this->removeObsolete($templateCode, 0, [], [], $stats);
            $this->resource->commit();
        } catch (Throwable $exception) {
            $this->resource->rollBack();
            throw $exception;
        }
    }

    /**
     * @param int[] $expectedGroupIds
     * @param int[] $expectedAttributeIds
     * @param array<string, int> $stats
     */
    public function removeObsolete(
        string $templateCode,
        int $currentAttributeSetId,
        array $expectedGroupIds,
        array $expectedAttributeIds,
        array &$stats
    ): void {
        $plan = $this->planProvider->getPlan(
            $templateCode,
            $currentAttributeSetId,
            $expectedGroupIds,
            $expectedAttributeIds
        );
        $this->removeAttributes($templateCode, $plan['attributes'], $stats);
        $this->removeGroups($templateCode, $plan['groups'], $stats);
    }

    /**
     * @param array<int, array<string, int|string|bool>> $ownerships
     * @param array<string, int> $stats
     */
    private function removeAttributes(
        string $templateCode,
        array $ownerships,
        array &$stats
    ): void {
        foreach ($ownerships as $ownership) {
            if (!empty($ownership['protected'])) {
                $this->ownershipResource->deleteAttributeOwnership($ownership['ownership_id']);
                $this->changeReport->add(
                    'template_attribute',
                    $templateCode . '::obsolete::' . $ownership['ergonode_attribute_code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Preserved protected or manually managed placement and released template ownership.',
                    [
                        'attribute_set_id' => $ownership['attribute_set_id'],
                        'attribute_group_id' => $ownership['attribute_group_id'],
                        'entity_attribute_id' => $ownership['entity_attribute_id'],
                        'magento_attribute_id' => $ownership['magento_attribute_id'],
                        'magento_attribute_code' => $ownership['magento_attribute_code'],
                    ]
                );
                continue;
            }

            $deleted = $this->resource->deleteEntityAttribute($ownership['entity_attribute_id']);
            $this->ownershipResource->deleteAttributeOwnership($ownership['ownership_id']);
            if ($deleted === 0) {
                $this->changeReport->add(
                    'template_attribute',
                    $templateCode . '::obsolete::' . $ownership['ergonode_attribute_code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Dropped stale template attribute ownership because the Magento placement no longer exists.',
                    [
                        'attribute_set_id' => $ownership['attribute_set_id'],
                        'attribute_group_id' => $ownership['attribute_group_id'],
                        'entity_attribute_id' => $ownership['entity_attribute_id'],
                        'magento_attribute_id' => $ownership['magento_attribute_id'],
                        'magento_attribute_code' => $ownership['magento_attribute_code'],
                    ]
                );
                continue;
            }

            $stats['attributes_removed']++;
            $this->changeReport->add(
                'template_attribute',
                $templateCode . '::obsolete::' . $ownership['ergonode_attribute_code'],
                ChangeReport::ACTION_UPDATED,
                'Removed obsolete Magento entity attribute owned by the Ergonode template.',
                [
                    'attribute_set_id' => $ownership['attribute_set_id'],
                    'attribute_group_id' => $ownership['attribute_group_id'],
                    'entity_attribute_id' => $ownership['entity_attribute_id'],
                    'magento_attribute_id' => $ownership['magento_attribute_id'],
                    'magento_attribute_code' => $ownership['magento_attribute_code'],
                ]
            );
        }
    }

    /**
     * @param array<int, array<string, int|string>> $ownerships
     * @param array<string, int> $stats
     */
    private function removeGroups(
        string $templateCode,
        array $ownerships,
        array &$stats
    ): void {
        foreach ($ownerships as $ownership) {
            $groupId = $ownership['attribute_group_id'];
            $remainingAttributeCount = $ownership['remaining_attributes'];
            if ($remainingAttributeCount > 0) {
                $this->ownershipResource->deleteGroupOwnership($ownership['ownership_id']);
                $this->changeReport->add(
                    'template_group',
                    $templateCode . '::obsolete::' . $ownership['section_code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Dropped obsolete template group ownership because the group contains foreign attributes.',
                    [
                        'attribute_set_id' => $ownership['attribute_set_id'],
                        'attribute_group_id' => $groupId,
                        'attribute_group_code' => $ownership['attribute_group_code'],
                        'remaining_attributes' => $remainingAttributeCount,
                    ]
                );
                continue;
            }

            $this->resource->deleteAttributeGroup($groupId);
            $this->ownershipResource->deleteGroupOwnership($ownership['ownership_id']);
            $stats['groups_deleted']++;
            $this->changeReport->add(
                'template_group',
                $templateCode . '::obsolete::' . $ownership['section_code'],
                ChangeReport::ACTION_UPDATED,
                'Deleted obsolete empty Magento attribute group owned by the Ergonode template.',
                [
                    'attribute_set_id' => $ownership['attribute_set_id'],
                    'attribute_group_id' => $groupId,
                    'attribute_group_code' => $ownership['attribute_group_code'],
                    'attribute_group_name' => $ownership['attribute_group_name'],
                ]
            );
        }
    }
}
