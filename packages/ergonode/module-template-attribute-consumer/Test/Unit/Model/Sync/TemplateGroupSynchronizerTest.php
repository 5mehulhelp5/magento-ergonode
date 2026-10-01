<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupCodeOwnerProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupCodeResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateMappedGroupResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureNamer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use PHPUnit\Framework\TestCase;

class TemplateGroupSynchronizerTest extends TestCase
{
    public function testFullSynchronizerAlwaysCreatesAndMapsMissingSectionGroup(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('findAttributeGroupByCode')
            ->with(12, 'ergonode_product_details')
            ->willReturn(null);
        $resource->expects($this->once())
            ->method('insertAttributeGroup')
            ->with([
                'attribute_set_id' => 12,
                'attribute_group_name' => 'Ergonode - Dane techniczne',
                'sort_order' => 20,
                'attribute_group_code' => 'ergonode_product_details',
                'tab_group_code' => 'Ergonode - Dane techniczne',
            ])
            ->willReturn(99);

        $templateCacheProvider = $this->createMock(TemplateCacheProvider::class);
        $templateCacheProvider->expects($this->once())
            ->method('saveSectionGroupId')
            ->with('product', 'product_details', 99);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('saveGroupOwnership')
            ->with('product', 12, 'product_details', 99);

        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_group',
                'product::product_details',
                ChangeReport::ACTION_INSERTED,
                'Created Magento attribute group for Ergonode section.',
                [
                    'attribute_set_id' => 12,
                    'attribute_group_id' => 99,
                    'attribute_group_code' => 'ergonode_product_details',
                    'attribute_group_name' => 'Ergonode - Dane techniczne',
                    'sort_order' => 20,
                ]
            );

        $synchronizer = new TemplateGroupSynchronizer(
            $resource,
            $ownershipResource,
            new TemplateMappedGroupResolver($resource, $ownershipResource),
            new TemplateGroupCodeResolver(
                new TemplateStructureNamer(),
                new TemplateGroupCodeOwnerProvider($ownershipResource)
            ),
            new TemplateStructureNamer(),
            $templateCacheProvider,
            $changeReport
        );
        $stats = ['groups_created' => 0];
        $claimedAttributeGroupIds = [];

        self::assertSame(99, $synchronizer->ensure(
            'product',
            12,
            [
                'code' => 'product_details',
                'name' => 'Dane techniczne',
                'is_synthetic' => false,
                'attribute_group_id' => null,
                'sort_order' => 20,
            ],
            $stats,
            $claimedAttributeGroupIds
        ));
        self::assertSame(1, $stats['groups_created']);
        self::assertSame([99 => true], $claimedAttributeGroupIds);
    }

    public function testRenamesPreviouslyCreatedManagedGroupFromTranslatedSectionName(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('findAttributeGroupById')
            ->with(12, 99)
            ->willReturn([
                'attribute_group_id' => 99,
                'attribute_group_code' => 'ergonode_product_details',
                'attribute_group_name' => 'Ergonode - Product Details',
                'sort_order' => 20,
            ]);
        $resource->expects($this->once())
            ->method('updateAttributeGroup')
            ->with(99, [
                'attribute_group_name' => 'Ergonode - Dane techniczne',
                'tab_group_code' => 'Ergonode - Dane techniczne',
            ]);

        $templateCacheProvider = $this->createMock(TemplateCacheProvider::class);
        $templateCacheProvider->expects($this->never())->method('saveSectionGroupId');
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('findGroupOwnerById')
            ->with(99)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => 'product_details',
            ]);
        $ownershipResource->expects($this->never())->method('saveGroupOwnership');

        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_group',
                'product::product_details',
                ChangeReport::ACTION_UPDATED,
                'Updated Magento attribute group for Ergonode section.',
                [
                    'attribute_set_id' => 12,
                    'attribute_group_id' => 99,
                    'changes' => [
                        'attribute_group_name' => 'Ergonode - Dane techniczne',
                        'tab_group_code' => 'Ergonode - Dane techniczne',
                    ],
                ]
            );

        $synchronizer = new TemplateGroupSynchronizer(
            $resource,
            $ownershipResource,
            new TemplateMappedGroupResolver($resource, $ownershipResource),
            new TemplateGroupCodeResolver(
                new TemplateStructureNamer(),
                new TemplateGroupCodeOwnerProvider($ownershipResource)
            ),
            new TemplateStructureNamer(),
            $templateCacheProvider,
            $changeReport
        );
        $stats = ['groups_updated' => 0];
        $claimedAttributeGroupIds = [];

        self::assertSame(99, $synchronizer->ensure(
            'product',
            12,
            [
                'code' => 'product_details',
                'name' => 'Dane techniczne',
                'is_synthetic' => false,
                'attribute_group_id' => 99,
                'sort_order' => 20,
            ],
            $stats,
            $claimedAttributeGroupIds
        ));
        self::assertSame(1, $stats['groups_updated']);
        self::assertSame([99 => true], $claimedAttributeGroupIds);
    }

    public function testRenamesOwnedLegacySyntheticGroupUsingReservedContract(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->method('findAttributeGroupById')->willReturn([
            'attribute_group_id' => 99,
            'attribute_group_code' => 'ergonode_legacy',
            'attribute_group_name' => 'Ergonode',
            'sort_order' => 1000,
        ]);
        $resource->expects($this->once())
            ->method('updateAttributeGroup')
            ->with(99, [
                'attribute_group_code' => 'ergonode_internal_unassigned_attributes',
                'attribute_group_name' => 'Ergonode - Unassigned',
                'tab_group_code' => 'Ergonode - Unassigned',
        ]);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('findGroupOwnerById')
            ->with(99)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => '__internal_unassigned_attributes__',
            ]);
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())->method('add');
        $synchronizer = new TemplateGroupSynchronizer(
            $resource,
            $ownershipResource,
            new TemplateMappedGroupResolver($resource, $ownershipResource),
            new TemplateGroupCodeResolver(
                new TemplateStructureNamer(),
                new TemplateGroupCodeOwnerProvider($ownershipResource)
            ),
            new TemplateStructureNamer(),
            $this->createStub(TemplateCacheProvider::class),
            $changeReport
        );
        $stats = ['groups_updated' => 0];
        $claimedAttributeGroupIds = [];

        self::assertSame(99, $synchronizer->ensure(
            'product',
            12,
            [
                'code' => '__internal_unassigned_attributes__',
                'name' => '__internal_unassigned_attributes__',
                'is_synthetic' => true,
                'attribute_group_id' => 99,
                'sort_order' => 1000,
            ],
            $stats,
            $claimedAttributeGroupIds
        ));
        self::assertSame(1, $stats['groups_updated']);
        self::assertSame([99 => true], $claimedAttributeGroupIds);
    }

    public function testUpdatesMappedGroupCodeOnlyAfterResolverConfirmsAvailability(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('findAttributeGroupById')
            ->with(12, 99)
            ->willReturn([
                'attribute_group_id' => 99,
                'attribute_group_code' => 'legacy_group',
                'attribute_group_name' => 'Ergonode - General',
                'sort_order' => 20,
            ]);
        $resource->expects($this->never())->method('findAttributeGroupByCode');
        $resource->expects($this->once())
            ->method('updateAttributeGroup')
            ->with(99, ['attribute_group_code' => 'ergonode_general']);

        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('findGroupOwnerById')
            ->with(99)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => 'general',
            ]);
        $ownershipResource->expects($this->once())
            ->method('findGroupCodeOwner')
            ->with(12, 'ergonode_general')
            ->willReturn(null);
        $ownershipResource->expects($this->never())->method('saveGroupOwnership');
        $templateCacheProvider = $this->createMock(TemplateCacheProvider::class);
        $templateCacheProvider->expects($this->never())->method('saveSectionGroupId');
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_group',
                'product::general',
                ChangeReport::ACTION_UPDATED,
                'Updated Magento attribute group for Ergonode section.',
                [
                    'attribute_set_id' => 12,
                    'attribute_group_id' => 99,
                    'changes' => ['attribute_group_code' => 'ergonode_general'],
                ]
            );

        $synchronizer = new TemplateGroupSynchronizer(
            $resource,
            $ownershipResource,
            new TemplateMappedGroupResolver($resource, $ownershipResource),
            new TemplateGroupCodeResolver(
                new TemplateStructureNamer(),
                new TemplateGroupCodeOwnerProvider($ownershipResource)
            ),
            new TemplateStructureNamer(),
            $templateCacheProvider,
            $changeReport
        );
        $stats = ['groups_updated' => 0];
        $claimedAttributeGroupIds = [];

        self::assertSame(99, $synchronizer->ensure(
            'product',
            12,
            [
                'code' => 'general',
                'name' => 'General',
                'is_synthetic' => false,
                'attribute_group_id' => 99,
                'sort_order' => 20,
            ],
            $stats,
            $claimedAttributeGroupIds
        ));
        self::assertSame(1, $stats['groups_updated']);
        self::assertSame([99 => true], $claimedAttributeGroupIds);
    }

    public function testKeepsMappedGroupIdempotentlyOnRepeatedSync(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('findAttributeGroupById')
            ->with(12, 99)
            ->willReturn([
                'attribute_group_id' => 99,
                'attribute_group_code' => 'ergonode_general',
                'attribute_group_name' => 'Ergonode - General',
                'sort_order' => 20,
            ]);
        $resource->expects($this->never())->method('findAttributeGroupByCode');
        $resource->expects($this->never())->method('updateAttributeGroup');

        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('findGroupOwnerById')
            ->with(99)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => 'general',
            ]);
        $ownershipResource->expects($this->once())
            ->method('findGroupCodeOwner')
            ->willReturn([
                'attribute_group_id' => 99,
                'template_code' => 'product',
                'section_code' => 'general',
            ]);
        $ownershipResource->expects($this->never())->method('saveGroupOwnership');
        $templateCacheProvider = $this->createMock(TemplateCacheProvider::class);
        $templateCacheProvider->expects($this->never())->method('saveSectionGroupId');
        $changeReport = $this->createMock(ChangeReport::class);
        $changeReport->expects($this->once())
            ->method('add')
            ->with(
                'template_group',
                'product::general',
                ChangeReport::ACTION_UNCHANGED,
                'Magento attribute group is unchanged.',
                [
                    'attribute_set_id' => 12,
                    'attribute_group_id' => 99,
                    'attribute_group_code' => 'ergonode_general',
                ]
            );

        $synchronizer = new TemplateGroupSynchronizer(
            $resource,
            $ownershipResource,
            new TemplateMappedGroupResolver($resource, $ownershipResource),
            new TemplateGroupCodeResolver(
                new TemplateStructureNamer(),
                new TemplateGroupCodeOwnerProvider($ownershipResource)
            ),
            new TemplateStructureNamer(),
            $templateCacheProvider,
            $changeReport
        );
        $stats = ['groups_updated' => 0];
        $claimedAttributeGroupIds = [];

        self::assertSame(99, $synchronizer->ensure(
            'product',
            12,
            [
                'code' => 'general',
                'name' => 'General',
                'is_synthetic' => false,
                'attribute_group_id' => 99,
                'sort_order' => 20,
            ],
            $stats,
            $claimedAttributeGroupIds
        ));
        self::assertSame(0, $stats['groups_updated']);
        self::assertSame([99 => true], $claimedAttributeGroupIds);
    }
}
