<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleanupPlanProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateStructureCleanerTest extends TestCase
{
    public function testDeletedTemplateCleanupRollsBackOnFailure(): void
    {
        $plan = $this->createStub(TemplateStructureCleanupPlanProvider::class);
        $plan->method('getPlan')->willThrowException(new RuntimeException('Database failure'));
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects(self::once())->method('beginTransaction');
        $resource->expects(self::once())->method('rollBack');
        $resource->expects(self::never())->method('commit');
        $this->expectException(RuntimeException::class);
        (new TemplateStructureCleaner(
            $resource,
            $this->createStub(TemplateStructureOwnershipResource::class),
            $plan,
            $this->createStub(ChangeReport::class)
        ))->removeTemplate('deleted');
    }

    public function testAppliesOwnedCleanupPlanAndPreservesGroupWithForeignAttributes(): void
    {
        $planProvider = $this->createMock(TemplateStructureCleanupPlanProvider::class);
        $planProvider->expects($this->once())
            ->method('getPlan')
            ->with('product', 12, [18], [41])
            ->willReturn([
                'attributes' => [
                    $this->attributeOwnership(2, 'removed', 42, 19, 72, 12, true),
                    $this->attributeOwnership(3, 'old', 43, 20, 73, 13, true),
                ],
                'groups' => [
                    $this->groupOwnership(12, 'manual', 19, 12, 1),
                    $this->groupOwnership(13, 'old', 20, 13, 0),
                ],
            ]);
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->exactly(2))
            ->method('deleteEntityAttribute')
            ->willReturnMap([[72, 1], [73, 1]]);
        $resource->expects($this->once())->method('deleteAttributeGroup')->with(20);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->exactly(2))
            ->method('deleteAttributeOwnership')
            ->with(self::logicalOr(2, 3));
        $ownershipResource->expects($this->exactly(2))
            ->method('deleteGroupOwnership')
            ->with(self::logicalOr(12, 13));
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->exactly(4))->method('add');
        $stats = ['attributes_removed' => 0, 'groups_deleted' => 0];

        (new TemplateStructureCleaner($resource, $ownershipResource, $planProvider, $changeReport))
            ->removeObsolete('product', 12, [18], [41], $stats);

        self::assertSame(2, $stats['attributes_removed']);
        self::assertSame(1, $stats['groups_deleted']);
    }

    public function testDropsStaleAttributeOwnershipWithoutIncrementingRemovalStats(): void
    {
        $planProvider = $this->createStub(TemplateStructureCleanupPlanProvider::class);
        $planProvider->method('getPlan')->willReturn([
            'attributes' => [$this->attributeOwnership(2, 'removed', 42, 19, 72, 12, false)],
            'groups' => [],
        ]);
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())->method('deleteEntityAttribute')->with(72)->willReturn(0);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())->method('deleteAttributeOwnership')->with(2);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_attribute',
                'product::obsolete::removed',
                ChangeReport::ACTION_SKIPPED,
                'Dropped stale template attribute ownership because the Magento placement no longer exists.',
                self::isArray()
            );
        $stats = ['attributes_removed' => 0, 'groups_deleted' => 0];

        (new TemplateStructureCleaner($resource, $ownershipResource, $planProvider, $changeReport))
            ->removeObsolete('product', 12, [], [], $stats);

        self::assertSame(0, $stats['attributes_removed']);
    }

    public function testDropsOwnershipButPreservesProtectedAttributePlacement(): void
    {
        $planProvider = $this->createStub(TemplateStructureCleanupPlanProvider::class);
        $ownership = $this->attributeOwnership(2, 'price', 75, 19, 72, 12, true);
        $ownership['protected'] = true;
        $planProvider->method('getPlan')->willReturn([
            'attributes' => [$ownership],
            'groups' => [],
        ]);
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->never())->method('deleteEntityAttribute');
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())->method('deleteAttributeOwnership')->with(2);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_attribute',
                'product::obsolete::price',
                ChangeReport::ACTION_SKIPPED,
                'Preserved protected or manually managed placement and released template ownership.',
                self::isArray()
            );
        $stats = ['attributes_removed' => 0, 'groups_deleted' => 0];

        (new TemplateStructureCleaner($resource, $ownershipResource, $planProvider, $changeReport))
            ->removeObsolete('product', 12, [], [], $stats);

        self::assertSame(0, $stats['attributes_removed']);
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function attributeOwnership(
        int $ownershipId,
        string $ergonodeCode,
        int $attributeId,
        int $groupId,
        int $entityAttributeId,
        int $attributeSetId,
        bool $placementExists
    ): array {
        return [
            'ownership_id' => $ownershipId,
            'ergonode_attribute_code' => $ergonodeCode,
            'magento_attribute_id' => $attributeId,
            'magento_attribute_code' => $ergonodeCode,
            'attribute_group_id' => $groupId,
            'entity_attribute_id' => $entityAttributeId,
            'attribute_set_id' => $attributeSetId,
            'placement_exists' => $placementExists,
            'protected' => false,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function groupOwnership(
        int $ownershipId,
        string $sectionCode,
        int $groupId,
        int $attributeSetId,
        int $remainingAttributes
    ): array {
        return [
            'ownership_id' => $ownershipId,
            'section_code' => $sectionCode,
            'attribute_group_id' => $groupId,
            'attribute_group_code' => 'ergonode_' . $sectionCode,
            'attribute_group_name' => 'Ergonode - ' . $sectionCode,
            'attribute_set_id' => $attributeSetId,
            'remaining_attributes' => $remainingAttributes,
        ];
    }
}
