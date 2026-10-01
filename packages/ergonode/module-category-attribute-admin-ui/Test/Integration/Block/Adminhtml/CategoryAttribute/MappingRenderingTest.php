<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryAttribute\Test\Integration;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryAttribute\Mapping;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true)]
class MappingRenderingTest extends TestCase
{
    public function testSharedTemplateRendersDraftCompatibleAndIncompatibleMappings(): void
    {
        $source = ['code' => 'source', 'label' => 'Source', 'type' => 'text', 'scope' => 'global'];
        $target = ['code' => 'target', 'label' => 'Target', 'type' => 'text', 'scope' => 'global'];
        $provider = $this->createStub(MappingProvider::class);
        $provider->method('getErgonodeAttributes')->willReturn([$source]);
        $provider->method('getMagentoAttributes')->willReturn([$target]);
        $provider->method('getAttributeMappings')->willReturn([
            ['mapping_id' => 1, 'left' => $source, 'right' => null, 'tone' => 'warning'],
            ['mapping_id' => 2, 'left' => $source, 'right' => $target, 'tone' => 'ok'],
            ['mapping_id' => 3, 'left' => $source, 'right' => $target, 'tone' => 'error'],
        ]);
        $provider->method('getOptionMappingProgress')->willReturn([]);
        $manager = Bootstrap::getObjectManager();
        $layout = $manager->create(LayoutInterface::class);
        $block = $layout->createBlock(Mapping::class, '', ['managementProvider' => $provider]);
        $block->setTemplate('Ergonode_CoreAdminUi::attribute/mapping.phtml');

        $config = json_decode($block->getMappingConfigJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains('select', $config['attribute_type_compatibility']['select']);
        self::assertNotContains('date', $config['attribute_type_compatibility']['select']);
        $html = $block->toHtml();

        self::assertStringContainsString('ergonode-category-attribute-mapping', $html);
        self::assertStringContainsString('vea-status-tone-warning', $html);
        self::assertStringContainsString('vea-status-tone-ok', $html);
        self::assertStringContainsString('vea-status-tone-error', $html);
        self::assertSame(3, substr_count($html, 'data-role="mapping-row"'));
    }
}
