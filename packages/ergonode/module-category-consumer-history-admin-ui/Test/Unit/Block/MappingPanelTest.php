<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory\Test\Unit;

use Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory\MappingPanel;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MappingPanelTest extends TestCase
{
    public function testPanelRequiresHistoryPermission(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('isAllowed')
            ->with('Ergonode_CategoryConsumerHistory::view')->willReturn(false);
        $block = $this->getMockBuilder(MappingPanel::class)
            ->disableOriginalConstructor()->onlyMethods(['getAuthorization'])->getMock();
        $block->expects(self::once())->method('getAuthorization')->willReturn($authorization);

        self::assertFalse($block->canViewHistory());
    }

    public function testInitialPanelContainsOnlyEndpointUrls(): void
    {
        $block = $this->getMockBuilder(MappingPanel::class)
            ->disableOriginalConstructor()->onlyMethods(['getUrl'])->getMock();
        $block->expects(self::exactly(2))->method('getUrl')->willReturnArgument(0);
        (new ReflectionProperty(MappingPanel::class, 'json'))->setValue($block, new Json());

        self::assertSame([
            'urls' => [
                'operations' => 'ergonode/category_tree_history/operations',
                'history' => 'ergonode/category_tree_history/index',
            ],
        ], json_decode($block->getConfigJson(), true, 512, JSON_THROW_ON_ERROR));
    }
}
