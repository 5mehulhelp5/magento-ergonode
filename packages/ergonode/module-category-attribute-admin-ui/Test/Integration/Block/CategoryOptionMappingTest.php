<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryOption\Test\Integration;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryOption\Mapping;
use Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Option\AutoMatch;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true)]
class CategoryOptionMappingTest extends TestCase
{
    public function testCategoryWorkspaceProvidesAutoMatchRouteAndControllerResolves(): void
    {
        $provider = $this->createStub(MappingProvider::class);
        $provider->method('getOptionContext')->willReturn([
            'context' => ['mapping_id' => 7, 'left' => [], 'right' => []], 'contexts' => [],
        ]);
        $objects = Bootstrap::getObjectManager();
        $block = $objects->create(LayoutInterface::class)->createBlock(
            Mapping::class,
            '',
            ['managementProvider' => $provider]
        );
        $config = $objects->get(Json::class)->unserialize($block->getMappingConfigJson());

        self::assertSame(7, $config['attribute_mapping_id']);
        self::assertStringContainsString('/ergonode/category_option/autoMatch/', $config['urls']['auto_match']);
        self::assertInstanceOf(AutoMatch::class, $objects->create(AutoMatch::class));
    }
}
