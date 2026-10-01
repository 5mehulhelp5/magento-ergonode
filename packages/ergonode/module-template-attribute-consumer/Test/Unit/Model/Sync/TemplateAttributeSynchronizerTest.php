<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\ManualPlacementResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateAttributeSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use PHPUnit\Framework\TestCase;

class TemplateAttributeSynchronizerTest extends TestCase
{
    public function testInsertsMissingAttributePlacementAndUpdatesState(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('insertEntityAttribute')
            ->with(4, 12, 18, 41, 3)
            ->willReturn(77);
        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('saveAttributeOwnership')
            ->with('product', 12, 'color', 41, 18, 77);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_attribute',
                'product::general::color',
                ChangeReport::ACTION_INSERTED,
                'Inserted Magento entity attribute into template group.',
                self::isArray()
            );
        $synchronizer = new TemplateAttributeSynchronizer(
            $resource,
            $ownershipResource,
            $attributeSetManager,
            $changeReport,
            $this->createStub(ManualPlacementResource::class)
        );
        $existingRows = [];
        $stats = ['attributes_inserted' => 0];

        $synchronizer->sync(
            12,
            18,
            41,
            'product',
            'general',
            'color',
            'color',
            3,
            $existingRows,
            $stats
        );

        self::assertSame([
            41 => [
                'entity_attribute_id' => 77,
                'attribute_group_id' => 18,
                'sort_order' => 3,
            ],
        ], $existingRows);
        self::assertSame(1, $stats['attributes_inserted']);
    }

    public function testRegistersMovedAttributePlacement(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('updateEntityAttribute')
            ->with(77, ['attribute_group_id' => 18]);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('saveAttributeOwnership')
            ->with('product', 12, 'color', 41, 18, 77);
        $synchronizer = new TemplateAttributeSynchronizer(
            $resource,
            $ownershipResource,
            $this->createStub(AttributeSetManager::class),
            $this->createStub(ChangeReport::class),
            $this->createStub(ManualPlacementResource::class)
        );
        $existingRows = [
            41 => [
                'entity_attribute_id' => 77,
                'attribute_group_id' => 10,
                'sort_order' => 3,
            ],
        ];
        $stats = ['attributes_moved' => 0];

        $synchronizer->sync(12, 18, 41, 'product', 'general', 'color', 'color', 3, $existingRows, $stats);

        self::assertSame(18, $existingRows[41]['attribute_group_id']);
        self::assertSame(1, $stats['attributes_moved']);
    }

    public function testClaimsTemplateManagedAttributeWhenOnlySortOrderChanges(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('updateEntityAttribute')
            ->with(77, ['sort_order' => 9]);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('saveAttributeOwnership')
            ->with('product', 12, 'color', 41, 18, 77);
        $synchronizer = new TemplateAttributeSynchronizer(
            $resource,
            $ownershipResource,
            $this->createStub(AttributeSetManager::class),
            $this->createStub(ChangeReport::class),
            $this->createStub(ManualPlacementResource::class)
        );
        $existingRows = [
            41 => [
                'entity_attribute_id' => 77,
                'attribute_group_id' => 18,
                'sort_order' => 3,
            ],
        ];
        $stats = ['attributes_moved' => 0];

        $synchronizer->sync(12, 18, 41, 'product', 'general', 'color', 'color', 9, $existingRows, $stats);

        self::assertSame(9, $existingRows[41]['sort_order']);
        self::assertSame(1, $stats['attributes_moved']);
    }
}
