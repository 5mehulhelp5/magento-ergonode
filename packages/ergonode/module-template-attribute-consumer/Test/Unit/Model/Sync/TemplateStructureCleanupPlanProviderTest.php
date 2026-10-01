<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleanupPlanProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateStructureCleanupPlanProviderTest extends TestCase
{
    public function testPlansCurrentAndPreviousSetsAndCountsOnlyForeignAttributes(): void
    {
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->method('loadAttributeSetIds')->with('product')->willReturn([12, 13]);
        $ownershipResource->method('loadAttributeOwnerships')->willReturnMap([
            ['product', 12, [
                $this->attributeOwnership(1, 'kept', 41, 18, 71),
                $this->attributeOwnership(2, 'removed', 42, 19, 72),
            ]],
            ['product', 13, [$this->attributeOwnership(3, 'old', 43, 20, 73)]],
        ]);
        $ownershipResource->method('loadGroupOwnerships')->willReturnMap([
            ['product', 12, [
                $this->groupOwnership(11, 'kept', 18),
                $this->groupOwnership(12, 'manual', 19),
            ]],
            ['product', 13, [$this->groupOwnership(13, 'old', 20)]],
        ]);
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->exactly(2))
            ->method('entityAttributeExists')
            ->willReturnMap([[72, true], [73, true]]);
        $resource->method('countGroupAttributes')->willReturnMap([[12, 19, 2], [13, 20, 1]]);

        $plan = (new TemplateStructureCleanupPlanProvider(
            $resource,
            $ownershipResource,
            $this->unprotectedAttributePolicy(),
            $this->createStub(ManualPlacementResource::class)
        ))
            ->getPlan('product', 12, [18], [41]);

        self::assertSame([42, 43], array_column($plan['attributes'], 'magento_attribute_id'));
        self::assertSame([12, 13], array_column($plan['attributes'], 'attribute_set_id'));
        self::assertSame([1, 0], array_column($plan['groups'], 'remaining_attributes'));
        self::assertSame([19, 20], array_column($plan['groups'], 'attribute_group_id'));
    }

    public function testSubtractsProjectedAttributeMovesFromObsoleteGroupCount(): void
    {
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->method('loadAttributeSetIds')->with('product')->willReturn([12]);
        $ownershipResource->method('loadAttributeOwnerships')->with('product', 12)->willReturn([
            $this->attributeOwnership(1, 'moved', 41, 19, 71),
        ]);
        $ownershipResource->method('loadGroupOwnerships')->with('product', 12)->willReturn([
            $this->groupOwnership(11, 'obsolete', 19),
        ]);
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())->method('countGroupAttributes')->with(12, 19)->willReturn(1);

        $plan = (new TemplateStructureCleanupPlanProvider(
            $resource,
            $ownershipResource,
            $this->unprotectedAttributePolicy(),
            $this->createStub(ManualPlacementResource::class)
        ))
            ->getPlan('product', 12, [], [41], [19 => 1]);

        self::assertSame([], $plan['attributes']);
        self::assertSame(0, $plan['groups'][0]['remaining_attributes']);
    }

    #[DataProvider('protectedPlacements')]
    public function testProtectedAttributePlacementRemainsInOwnedGroupCount(
        bool $systemPolicy,
        bool $manualPlacement,
        bool $required
    ): void {
        $ownershipResource = $this->createStub(TemplateStructureOwnershipResource::class);
        $ownershipResource->method('loadAttributeSetIds')->willReturn([12]);
        $ownershipResource->method('loadAttributeOwnerships')->willReturn([
            $this->attributeOwnership(1, 'price', 75, 19, 71) + ['is_required' => $required],
        ]);
        $ownershipResource->method('loadGroupOwnerships')->willReturn([
            $this->groupOwnership(11, 'pricing', 19),
        ]);
        $resource = $this->createStub(TemplateStructureResource::class);
        $resource->method('entityAttributeExists')->willReturn(true);
        $resource->method('countGroupAttributes')->willReturn(1);
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturn($systemPolicy);
        $manual = $this->createStub(ManualPlacementResource::class);
        $manual->method('isManual')->willReturn($manualPlacement);

        $plan = (new TemplateStructureCleanupPlanProvider(
            $resource,
            $ownershipResource,
            $policy,
            $manual
        ))
            ->getPlan('product', 12, [], []);

        self::assertTrue($plan['attributes'][0]['protected']);
        self::assertSame(1, $plan['groups'][0]['remaining_attributes']);
    }

    public static function protectedPlacements(): array
    {
        return [
            'system policy' => [true, false, false],
            'manual placement' => [false, true, false],
            'required attribute' => [false, false, true],
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function attributeOwnership(
        int $ownershipId,
        string $ergonodeCode,
        int $attributeId,
        int $groupId,
        int $entityAttributeId
    ): array {
        return [
            'ownership_id' => $ownershipId,
            'ergonode_attribute_code' => $ergonodeCode,
            'magento_attribute_id' => $attributeId,
            'magento_attribute_code' => $ergonodeCode,
            'is_user_defined' => true,
            'attribute_group_id' => $groupId,
            'entity_attribute_id' => $entityAttributeId,
        ];
    }

    private function unprotectedAttributePolicy(): ProductAttributePlacementPolicyInterface
    {
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturn(false);

        return $policy;
    }

    /**
     * @return array<string, int|string>
     */
    private function groupOwnership(int $ownershipId, string $sectionCode, int $groupId): array
    {
        return [
            'ownership_id' => $ownershipId,
            'section_code' => $sectionCode,
            'attribute_group_id' => $groupId,
            'attribute_group_code' => 'ergonode_' . $sectionCode,
            'attribute_group_name' => 'Ergonode - ' . $sectionCode,
        ];
    }
}
