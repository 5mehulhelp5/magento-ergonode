<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Mapping;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Template\Model\TemplateCodeNormalizer;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Mapping\TemplateAttributeSetAutoMatcher;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use PHPUnit\Framework\TestCase;

class TemplateAttributeSetAutoMatcherTest extends TestCase
{
    public function testMapsOnlyOneUnusedAttributeSetWithMatchingNormalizedName(): void
    {
        $config = $this->createStub(TemplateConfigProvider::class);
        $config->method('shouldCreateAttributeSets')->willReturn(true);
        $normalizer = $this->createStub(TemplateCodeNormalizer::class);
        $normalizer->method('normalize')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                'Home Furniture' => 'home_furniture',
                'Already Used' => 'already_used',
                default => '',
            }
        );
        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductAttributeSets')->willReturn([
            ['id' => 12, 'name' => 'Home Furniture'],
            ['id' => 13, 'name' => 'Already Used'],
        ]);
        $cache = $this->createMock(TemplateCacheProvider::class);
        $cache->expects(self::once())->method('getAllTemplates')->with(true)->willReturn([
            ['entity_id' => 1, 'code' => 'home_furniture', 'attribute_set_id' => null, 'is_deleted' => false],
            ['entity_id' => 2, 'code' => 'legacy', 'attribute_set_id' => 13, 'is_deleted' => false],
        ]);
        $cache->expects(self::once())->method('assignAttributeSet')->with('home_furniture', 12);

        $stats = (new TemplateAttributeSetAutoMatcher(
            $config,
            $normalizer,
            $attributeSetManager,
            $cache,
            $this->createStub(ChangeReport::class)
        ))->match(['home_furniture', 'legacy']);

        self::assertSame(['matched' => 1, 'conflicts' => 0], $stats);
    }

    public function testSkipsAmbiguousNormalizedNames(): void
    {
        $config = $this->createStub(TemplateConfigProvider::class);
        $config->method('shouldCreateAttributeSets')->willReturn(true);
        $normalizer = $this->createStub(TemplateCodeNormalizer::class);
        $normalizer->method('normalize')->willReturn('home_furniture');
        $attributeSetManager = $this->createStub(AttributeSetManager::class);
        $attributeSetManager->method('getProductAttributeSets')->willReturn([
            ['id' => 12, 'name' => 'Home Furniture'],
            ['id' => 13, 'name' => 'Home-Furniture'],
        ]);
        $cache = $this->createMock(TemplateCacheProvider::class);
        $cache->method('getAllTemplates')->willReturn([
            ['entity_id' => 1, 'code' => 'home_furniture', 'attribute_set_id' => null, 'is_deleted' => false],
        ]);
        $cache->expects(self::never())->method('assignAttributeSet');

        $stats = (new TemplateAttributeSetAutoMatcher(
            $config,
            $normalizer,
            $attributeSetManager,
            $cache,
            $this->createStub(ChangeReport::class)
        ))->match(['home_furniture']);

        self::assertSame(['matched' => 0, 'conflicts' => 1], $stats);
    }
}
