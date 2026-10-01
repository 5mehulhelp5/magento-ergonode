<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupCodeOwnerProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureOwnershipResource;
use PHPUnit\Framework\TestCase;

class TemplateGroupCodeOwnerProviderTest extends TestCase
{
    public function testDelegatesOwnerLookupToPersistenceResource(): void
    {
        $owner = [
            'attribute_group_id' => 18,
            'template_code' => 'product',
            'section_code' => 'general',
        ];
        $resource = $this->createMock(TemplateStructureOwnershipResource::class);
        $resource->expects($this->once())
            ->method('findGroupCodeOwner')
            ->with(12, 'ergonode_general')
            ->willReturn($owner);

        self::assertSame(
            $owner,
            (new TemplateGroupCodeOwnerProvider($resource))->find(12, 'ergonode_general')
        );
    }
}
