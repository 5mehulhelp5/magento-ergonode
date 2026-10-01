<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Block\Adminhtml\Test\Unit;

use Ergonode\CategoryConsumerAdminUi\Model\Navigation\CategoryNavigationGroupProvider;
use Ergonode\CategoryAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\CategoryAttributeHistoryAdminUi\Block\Adminhtml\History;
use Ergonode\CategoryAttributeHistoryAdminUi\Model\HistoryView;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class HistoryTest extends TestCase
{
    public function testDeepLinkUsesHistoricalStateAndEscapesScriptClosingLabels(): void
    {
        $query = $this->createMock(HistoryQueryInterface::class);
        $query->expects(self::once())->method('getOperations')->willReturn([
            'items' => [['operation_id' => 100]], 'total' => 50, 'has_more' => true,
        ]);
        $query->expects(self::once())->method('getState')->with(42)->willReturn([
            'operation' => ['operation_id' => 42],
            'source' => [['code' => 'unsafe', 'label' => '</script><script>alert(1)</script>']],
            'target' => [], 'changes' => [['side' => 'source', 'code' => 'unsafe',
                'before' => null, 'after' => ['code' => 'unsafe']]],
        ]);
        $block = $this->block($query, 42, [['url' => '/mapping-with-current-key', 'is_current' => true]]);
        $json = $block->getConfigJson();
        $config = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('</script>', $json);
        self::assertSame(42, $config['state']['operation']['operation_id']);
        self::assertSame('/mapping-with-current-key', $config['urls']['mapping']);
    }

    public function testEmptyHistoryDoesNotReadCurrentStateOrExposeUnauthorizedMappingUrl(): void
    {
        $query = $this->createMock(HistoryQueryInterface::class);
        $query->expects(self::once())->method('getOperations')->willReturn([
            'items' => [], 'total' => 0, 'has_more' => false,
        ]);
        $query->expects(self::never())->method('getState');
        $block = $this->block($query, 0, [['url' => '/options-only', 'is_current' => false]]);
        $config = json_decode($block->getConfigJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertNull($config['state']);
        self::assertSame('', $config['urls']['mapping']);
    }

    /** @param list<array{url: string, is_current: bool}> $navigationItems */
    private function block(HistoryQueryInterface $query, int $operationId, array $navigationItems): History
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($operationId);
        $navigation = $this->createMock(CategoryNavigationGroupProvider::class);
        $navigation->expects(self::once())->method('getGroup')->with('category_attributes')
            ->willReturn(['items' => $navigationItems]);
        $block = $this->getMockBuilder(History::class)->disableOriginalConstructor()
            ->onlyMethods(['getRequest', 'getUrl', 'getViewFileUrl'])->getMock();
        $block->expects(self::once())->method('getRequest')->willReturn($request);
        $block->method('getUrl')->willReturnCallback(static fn (string $route): string => '/' . $route);
        $block->method('getViewFileUrl')->willReturn('/icon.svg');
        (new ReflectionProperty(History::class, 'navigation'))->setValue($block, $navigation);
        (new ReflectionProperty(History::class, 'historyQuery'))->setValue($block, $query);
        (new ReflectionProperty(History::class, 'historyView'))->setValue($block, new HistoryView());
        (new ReflectionProperty(History::class, 'json'))->setValue($block, new JsonHexTag());

        return $block;
    }
}
