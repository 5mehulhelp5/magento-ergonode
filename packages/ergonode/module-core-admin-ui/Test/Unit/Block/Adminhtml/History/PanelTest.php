<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\History\Test\Unit;

use Ergonode\CoreAdminUi\Block\Adminhtml\History\Panel;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PanelTest extends TestCase
{
    public function testSidebarExposesOnlySummaryAndHistoryUrls(): void
    {
        $block = $this->getMockBuilder(Panel::class)
            ->disableOriginalConstructor()->onlyMethods(['getUrl'])->getMock();
        $block->setData('operations_route', 'ergonode/category_attribute_history/operations');
        $block->setData('history_route', 'ergonode/category_attribute_history/index');
        $block->expects(self::exactly(2))->method('getUrl')->willReturnArgument(0);
        (new ReflectionProperty(Panel::class, 'json'))->setValue($block, new JsonHexTag());

        self::assertSame([
            'urls' => [
                'operations' => 'ergonode/category_attribute_history/operations',
                'history' => 'ergonode/category_attribute_history/index',
            ],
        ], json_decode($block->getConfigJson(), true, 512, JSON_THROW_ON_ERROR));
    }
}
