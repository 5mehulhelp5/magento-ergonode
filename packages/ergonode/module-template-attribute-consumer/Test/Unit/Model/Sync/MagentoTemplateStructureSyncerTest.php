<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MappedTemplateAttributeResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateAttributeSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateGroupSynchronizer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateSyncPlanProvider;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Sync\TemplateAttributeSetResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MagentoTemplateStructureSyncerTest extends TestCase
{
    public function testDoesNotCreateGroupForSectionWithoutMappedAttributes(): void
    {
        $templateCacheProvider = $this->createStub(TemplateCacheProvider::class);
        $templateCacheProvider->method('getTemplateStructure')->willReturn([
            'entity_id' => 1,
            'code' => 'product',
            'attribute_set_id' => 12,
            'sections' => [
                [
                    'entity_id' => 2,
                    'code' => 'details',
                    'name' => 'Details',
                    'is_synthetic' => false,
                    'attribute_group_id' => null,
                    'sort_order' => 10,
                    'attributes' => [
                        ['code' => 'unmapped', 'sort_order' => 1],
                    ],
                ],
            ],
        ]);

        $attributeSetManager = $this->createMock(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);

        $resource = $this->createMock(TemplateStructureResource::class);
        $mappingProvider = $this->createMock(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->expects($this->once())
            ->method('getMagentoAttributeCodes')
            ->with(['unmapped'])
            ->willReturn([]);
        $resource->expects($this->once())->method('loadMagentoAttributes')->with(4, [])->willReturn([]);
        $resource->expects($this->once())->method('loadEntityAttributeRows')->with(12, [])->willReturn([]);
        $resource->expects($this->once())->method('beginTransaction');
        $resource->expects($this->once())->method('commit');
        $resource->expects($this->never())->method('rollBack');

        $groupSynchronizer = $this->createMock(TemplateGroupSynchronizer::class);
        $groupSynchronizer->expects($this->never())->method('ensure');
        $attributeSynchronizer = $this->createMock(TemplateAttributeSynchronizer::class);
        $attributeSynchronizer->expects($this->never())->method('sync');
        $changeReport = $this->createStub(ChangeReport::class);

        $synchronizer = new MagentoTemplateStructureSyncer(
            $templateCacheProvider,
            $changeReport,
            $resource,
            $groupSynchronizer,
            $attributeSynchronizer,
            $this->createStub(TemplateStructureCleaner::class),
            new TemplateSyncPlanProvider(
                $attributeSetManager,
                $resource,
                $mappingProvider,
                new MappedTemplateAttributeResolver(),
                $this->unprotectedAttributePolicy()
            ),
            $this->attributeSetResolver()
        );

        $stats = $synchronizer->syncTemplate('product');

        self::assertSame(1, $stats['templates_synced']);
        self::assertSame(1, $stats['groups_skipped']);
        self::assertSame(0, $stats['groups_created']);
        self::assertSame(1, $stats['attributes_skipped']);
        self::assertSame(0, $stats['attributes_inserted']);
    }

    public function testRollsBackAndDoesNotCleanPreviousSetsWhenNewStructureSyncFails(): void
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
                'sort_order' => 10,
                'attributes' => [['code' => 'mapped', 'sort_order' => 1]],
            ]],
        ]);
        $attributeSetManager = $this->createMock(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $resource = $this->createMock(TemplateStructureResource::class);
        $mappingProvider = $this->createStub(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->method('getMagentoAttributeCodes')->willReturn(['mapped' => 'mapped_magento']);
        $resource->method('loadMagentoAttributes')->willReturn([
            'mapped_magento' => ['attribute_id' => 33, 'is_user_defined' => true],
        ]);
        $resource->method('loadEntityAttributeRows')->willReturn([]);
        $resource->expects($this->once())->method('beginTransaction');
        $resource->expects($this->once())->method('rollBack');
        $resource->expects($this->never())->method('commit');
        $groupSynchronizer = $this->createMock(TemplateGroupSynchronizer::class);
        $groupSynchronizer->expects($this->once())
            ->method('ensure')
            ->willThrowException(new RuntimeException('new structure failed'));
        $attributeSynchronizer = $this->createMock(TemplateAttributeSynchronizer::class);
        $attributeSynchronizer->expects($this->never())->method('sync');
        $cleaner = $this->createMock(TemplateStructureCleaner::class);
        $cleaner->expects($this->never())->method('removeObsolete');
        $changeReport = $this->createStub(ChangeReport::class);
        $synchronizer = new MagentoTemplateStructureSyncer(
            $templateCacheProvider,
            $changeReport,
            $resource,
            $groupSynchronizer,
            $attributeSynchronizer,
            $cleaner,
            new TemplateSyncPlanProvider(
                $attributeSetManager,
                $resource,
                $mappingProvider,
                new MappedTemplateAttributeResolver(),
                $this->unprotectedAttributePolicy()
            ),
            $this->attributeSetResolver()
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('new structure failed');

        $synchronizer->syncTemplate('product', null, false, true);
    }

    public function testSkipsProductManagedAttributeWithoutCreatingOwnershipOrMovingIt(): void
    {
        $templateCacheProvider = $this->createStub(TemplateCacheProvider::class);
        $templateCacheProvider->method('getTemplateStructure')->willReturn([
            'entity_id' => 1,
            'code' => 'product',
            'attribute_set_id' => 12,
            'sections' => [[
                'entity_id' => 2,
                'code' => 'pricing',
                'name' => 'Pricing',
                'is_synthetic' => false,
                'attribute_group_id' => 18,
                'sort_order' => 10,
                'attributes' => [['code' => 'price', 'sort_order' => 30]],
            ]],
        ]);
        $attributeSetManager = $this->createMock(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $resource = $this->createMock(TemplateStructureResource::class);
        $mappingProvider = $this->createStub(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->method('getMagentoAttributeCodes')->willReturn(['price' => 'price']);
        $resource->method('loadMagentoAttributes')->willReturn([
            'price' => ['attribute_id' => 75, 'is_user_defined' => true],
        ]);
        $resource->expects($this->once())->method('loadEntityAttributeRows')->with(12, [])->willReturn([]);
        $resource->expects($this->once())->method('beginTransaction');
        $resource->expects($this->once())->method('commit');
        $groupSynchronizer = $this->createMock(TemplateGroupSynchronizer::class);
        $groupSynchronizer->expects($this->never())->method('ensure');
        $attributeSynchronizer = $this->createMock(TemplateAttributeSynchronizer::class);
        $attributeSynchronizer->expects($this->never())->method('sync');
        $changeReport = $this->createStub(ChangeReport::class);
        $productAttributePolicy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $productAttributePolicy->method('isProtected')->with('price')->willReturn(true);
        $synchronizer = new MagentoTemplateStructureSyncer(
            $templateCacheProvider,
            $changeReport,
            $resource,
            $groupSynchronizer,
            $attributeSynchronizer,
            $this->createStub(TemplateStructureCleaner::class),
            new TemplateSyncPlanProvider(
                $attributeSetManager,
                $resource,
                $mappingProvider,
                new MappedTemplateAttributeResolver(),
                $productAttributePolicy
            ),
            $this->attributeSetResolver()
        );

        self::assertSame(1, $synchronizer->syncTemplate('product')['templates_synced']);
    }

    private function unprotectedAttributePolicy(): ProductAttributePlacementPolicyInterface
    {
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturn(false);

        return $policy;
    }

    private function attributeSetResolver(): TemplateAttributeSetResolver
    {
        $resolver = $this->createStub(TemplateAttributeSetResolver::class);
        $resolver->method('resolveForSync')->willReturn(12);

        return $resolver;
    }
}
