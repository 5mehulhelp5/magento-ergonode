<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateMappedGroupResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use PHPUnit\Framework\TestCase;

class TemplateMappedGroupResolverTest extends TestCase
{
    public function testReturnsMappedGroupOwnedByRequestedSection(): void
    {
        $group = ['attribute_group_id' => 99, 'attribute_group_code' => 'ergonode_general'];
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->once())
            ->method('findAttributeGroupById')
            ->with(12, 99)
            ->willReturn($group);
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->once())
            ->method('findGroupOwnerById')
            ->with(99)
            ->willReturn([
                'template_code' => 'product',
                'attribute_set_id' => 12,
                'section_code' => 'general',
            ]);

        self::assertSame(
            ['group' => $group],
            (new TemplateMappedGroupResolver($resource, $ownershipResource))->resolve(
                'product',
                12,
                'general',
                99,
                []
            )
        );
    }

    public function testRejectsUnownedGroupEvenWithAnErgonodePrefix(): void
    {
        $group = ['attribute_group_id' => 99, 'attribute_group_code' => 'ergonode_general'];
        $resource = $this->createStub(TemplateStructureResource::class);
        $resource->method('findAttributeGroupById')->willReturn($group);
        $ownershipResource = $this->createStub(TemplateStructureOwnershipResource::class);
        $ownershipResource->method('findGroupOwnerById')->willReturn(null);

        self::assertSame(
            null,
            (new TemplateMappedGroupResolver($resource, $ownershipResource))->resolve(
                'product',
                12,
                'general',
                99,
                []
            )
        );
    }

    public function testRejectsGroupAlreadyClaimedByAnEarlierSection(): void
    {
        $resource = $this->createMock(TemplateStructureResource::class);
        $resource->expects($this->never())->method('findAttributeGroupById');
        $ownershipResource = $this->createMock(TemplateStructureOwnershipResource::class);
        $ownershipResource->expects($this->never())->method('findGroupOwnerById');

        self::assertNull(
            (new TemplateMappedGroupResolver($resource, $ownershipResource))->resolve(
                'product',
                12,
                'general',
                99,
                [99 => true]
            )
        );
    }

    public function testRejectsGroupOwnedByAnotherSection(): void
    {
        $resource = $this->createStub(TemplateStructureResource::class);
        $resource->method('findAttributeGroupById')->willReturn([
            'attribute_group_id' => 99,
            'attribute_group_code' => 'ergonode_general',
        ]);
        $ownershipResource = $this->createStub(TemplateStructureOwnershipResource::class);
        $ownershipResource->method('findGroupOwnerById')->willReturn([
            'template_code' => 'product',
            'attribute_set_id' => 12,
            'section_code' => 'details',
        ]);

        self::assertNull(
            (new TemplateMappedGroupResolver($resource, $ownershipResource))->resolve(
                'product',
                12,
                'general',
                99,
                []
            )
        );
    }
}
