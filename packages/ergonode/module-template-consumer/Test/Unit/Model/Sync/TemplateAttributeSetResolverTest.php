<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetResolver;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use PHPUnit\Framework\TestCase;

class TemplateAttributeSetResolverTest extends TestCase
{
    public function testSyncAssignsRequestedAttributeSet(): void
    {
        $cache = $this->createMock(TemplateCacheProvider::class);
        $manager = $this->createMock(AttributeSetManager::class);
        $report = $this->createMock(ChangeReport::class);
        $manager->expects($this->once())->method('validateProductAttributeSet')->with(12);
        $cache->expects($this->once())->method('assignAttributeSet')->with('product', 12);
        $report->expects($this->once())->method('add');
        $stats = ['attribute_sets_assigned' => 0, 'attribute_sets_unchanged' => 0];

        $attributeSetId = (new TemplateAttributeSetResolver(
            $cache,
            $manager,
            $report,
            $this->createStub(TemplateConfigProvider::class)
        ))->resolveForSync(['code' => 'product', 'attribute_set_id' => null], 12, false, $stats);

        self::assertSame(12, $attributeSetId);
        self::assertSame(1, $stats['attribute_sets_assigned']);
    }

    public function testSyncValidatesCreatedAttributeSetBeforeAssignment(): void
    {
        $cache = $this->createMock(TemplateCacheProvider::class);
        $manager = $this->createMock(AttributeSetManager::class);
        $config = $this->createStub(TemplateConfigProvider::class);
        $config->method('shouldCreateAttributeSets')->willReturn(true);
        $manager->expects($this->once())
            ->method('createOrGetForTemplate')
            ->with('product')
            ->willReturn(['attribute_set_id' => 33, 'created' => true]);
        $manager->expects($this->once())->method('validateProductAttributeSet')->with(33);
        $cache->expects($this->once())->method('assignAttributeSet')->with('product', 33);
        $stats = [
            'attribute_sets_created' => 0,
            'attribute_sets_assigned' => 0,
            'attribute_sets_unchanged' => 0,
        ];

        $attributeSetId = (new TemplateAttributeSetResolver(
            $cache,
            $manager,
            $this->createStub(ChangeReport::class),
            $config
        ))->resolveForSync(['code' => 'product', 'attribute_set_id' => null], null, true, $stats);

        self::assertSame(33, $attributeSetId);
        self::assertSame(1, $stats['attribute_sets_created']);
        self::assertSame(1, $stats['attribute_sets_assigned']);
    }
}
