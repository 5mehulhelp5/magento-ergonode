<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttributeConsumer\Model\Sync\CommonTemplateGroup;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateAttributeSetSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MappedTemplateAttributeResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateAttributeSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateSyncPlanProvider;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetResolver;
use PHPUnit\Framework\TestCase;

class MagentoTemplateAttributeSetSyncerTest extends TestCase
{
    public function testSynchronizesMappedAttributesIntoOneOwnedErgonodeGroupAndCleansObsoleteOnes(): void
    {
        $templateCacheProvider = $this->createStub(TemplateCacheProvider::class);
        $templateCacheProvider->method('getTemplateStructure')->willReturn([
            'entity_id' => 1,
            'code' => 'product',
            'attribute_set_id' => 12,
            'sections' => [[
                'entity_id' => 2,
                'code' => 'details',
                'name' => 'Details',
                'is_synthetic' => false,
                'attribute_group_id' => null,
                'sort_order' => 1,
                'attributes' => [
                    ['code' => 'color', 'sort_order' => 1],
                    ['code' => 'size', 'sort_order' => 2],
                    ['code' => 'missing', 'sort_order' => 3],
                ],
            ]],
        ]);

        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $resource = $this->createMock(TemplateStructureResource::class);
        $mappingProvider = $this->createStub(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->method('getMagentoAttributeCodes')->willReturn([
            'color' => 'color',
            'size' => 'size',
        ]);
        $resource->method('loadMagentoAttributes')->willReturn([
            'color' => ['attribute_id' => 41, 'is_user_defined' => true],
            'size' => ['attribute_id' => 42, 'is_user_defined' => true],
        ]);
        $resource->method('loadEntityAttributeRows')->willReturn([
            41 => [
                'entity_attribute_id' => 70,
                'attribute_group_id' => 7,
                'sort_order' => 4,
            ],
        ]);
        $resource->expects($this->once())->method('beginTransaction');
        $resource->expects($this->once())->method('commit');
        $resource->expects($this->never())->method('rollBack');

        $attributeSetResolver = $this->createMock(TemplateAttributeSetResolver::class);
        $attributeSetResolver->expects($this->once())
            ->method('resolveForSync')
            ->with(self::isArray(), null, false, self::isArray())
            ->willReturn(12);
        $groupSynchronizer = $this->createMock(TemplateGroupSynchronizer::class);
        $groupSynchronizer->expects($this->once())
            ->method('ensure')
            ->with(
                'product',
                12,
                self::callback(static fn (array $section): bool => $section['code']
                    === CommonTemplateGroup::SECTION_CODE),
                self::isArray(),
                self::isArray()
            )
            ->willReturn(99);
        $synced = [];
        $attributeSynchronizer = $this->createMock(TemplateAttributeSynchronizer::class);
        $attributeSynchronizer->expects($this->exactly(2))
            ->method('sync')
            ->willReturnCallback(static function (...$arguments) use (&$synced): void {
                $synced[] = array_slice($arguments, 0, 8);
            });
        $cleaner = $this->createMock(TemplateStructureCleaner::class);
        $cleaner->expects($this->once())
            ->method('removeObsolete')
            ->with('product', 12, [99], [41, 42], self::isArray());
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturn(false);

        $synchronizer = new MagentoTemplateAttributeSetSyncer(
            $templateCacheProvider,
            $this->createStub(ChangeReport::class),
            $resource,
            $groupSynchronizer,
            $attributeSynchronizer,
            $cleaner,
            new TemplateSyncPlanProvider(
                $attributeSetManager,
                $resource,
                $mappingProvider,
                new MappedTemplateAttributeResolver(),
                $policy
            ),
            $attributeSetResolver
        );

        $stats = $synchronizer->syncTemplate('product', false, true);

        self::assertSame(1, $stats['templates_synced']);
        self::assertSame(1, $stats['attributes_skipped']);
        self::assertSame([
            [12, 99, 41, 'product', 'details', 'color', 'color', 10],
            [12, 99, 42, 'product', 'details', 'size', 'size', 20],
        ], $synced);
    }
}
