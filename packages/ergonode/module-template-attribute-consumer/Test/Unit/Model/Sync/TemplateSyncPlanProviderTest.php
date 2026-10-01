<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Ergonode\ProductAttribute\Api\ProductAttributePlacementPolicyInterface;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MappedTemplateAttributeResolver;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureResource;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateSyncPlanProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use PHPUnit\Framework\TestCase;

class TemplateSyncPlanProviderTest extends TestCase
{
    public function testCreatesOneSharedMappingAndDeduplicationPlan(): void
    {
        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $resource = $this->createMock(TemplateStructureResource::class);
        $mappingProvider = $this->createMock(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->expects($this->once())
            ->method('getMagentoAttributeCodes')
            ->with(['color', 'missing', 'shade'])
            ->willReturn(['color' => 'catalog_color', 'shade' => 'catalog_color']);
        $resource->expects($this->once())
            ->method('loadMagentoAttributes')
            ->with(4, ['catalog_color', 'catalog_color'])
            ->willReturn(['catalog_color' => ['attribute_id' => 41, 'is_user_defined' => true]]);
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturn(false);
        $sections = [
            [
                'code' => 'primary',
                'attributes' => [
                    ['code' => 'color', 'sort_order' => 10],
                    ['code' => 'missing', 'sort_order' => 20],
                ],
            ],
            [
                'code' => 'secondary',
                'attributes' => [['code' => 'shade', 'sort_order' => 10]],
            ],
        ];

        $plan = (new TemplateSyncPlanProvider(
            $attributeSetManager,
            $resource,
            $mappingProvider,
            new MappedTemplateAttributeResolver(),
            $policy
        ))->create($sections);

        self::assertSame(['catalog_color' => 41], $plan['magento_attribute_ids']);
        self::assertSame('color', $plan['sections'][0]['mapped'][0]['ergonode_code']);
        self::assertSame('missing', $plan['sections'][0]['skipped'][0]['ergonode_code']);
        self::assertSame([], $plan['sections'][1]['mapped']);
        self::assertSame('shade', $plan['sections'][1]['duplicates'][0]['ergonode_code']);
    }

    public function testExcludesNativeAndProductManagedAttributesFromPlacementPlan(): void
    {
        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductEntityTypeId')->willReturn(4);
        $resource = $this->createStub(TemplateStructureResource::class);
        $mappingProvider = $this->createStub(ProductAttributeCodeMappingProviderInterface::class);
        $mappingProvider->method('getMagentoAttributeCodes')->willReturn([
            'remote_sku' => 'sku',
            'remote_color' => 'color',
        ]);
        $resource->method('loadMagentoAttributes')->willReturn([
            'sku' => ['attribute_id' => 4, 'is_user_defined' => false],
            'color' => ['attribute_id' => 41, 'is_user_defined' => true],
        ]);
        $policy = $this->createStub(ProductAttributePlacementPolicyInterface::class);
        $policy->method('isProtected')->willReturnCallback(
            static fn (string $code): bool => $code === 'sku'
        );

        $plan = (new TemplateSyncPlanProvider(
            $attributeSetManager,
            $resource,
            $mappingProvider,
            new MappedTemplateAttributeResolver(),
            $policy
        ))->create([[
            'code' => 'general',
            'attributes' => [
                ['code' => 'remote_sku', 'sort_order' => 10],
                ['code' => 'remote_color', 'sort_order' => 20],
            ],
        ]]);

        self::assertSame(['color' => 41], $plan['magento_attribute_ids']);
        self::assertSame('remote_color', $plan['sections'][0]['mapped'][0]['ergonode_code']);
        self::assertSame(
            MappedTemplateAttributeResolver::SKIP_SYSTEM_ATTRIBUTE,
            $plan['sections'][0]['skipped'][0]['reason']
        );
    }
}
