<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory\Test\Unit;

use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory\Index;
use Ergonode\CategoryConsumerHistoryAdminUi\Model\HistoryView;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class IndexTest extends TestCase
{
    public function testDeepLinkReadsOnlyTheRequestedSummaryOutsideTheFirstPage(): void
    {
        $query = $this->createMock(CategoryTreeHistoryQueryInterface::class);
        $query->expects(self::once())->method('getTrees')->willReturn($this->trees());
        $calls = [];
        $query->expects(self::exactly(2))->method('getOperationsPage')->willReturnCallback(
            function (int $treeId, int $limit, ?int $before = null) use (&$calls): array {
                $calls[] = [$treeId, $limit, $before];

                return $this->page($before === null ? 100 : 42);
            }
        );
        $query->expects(self::once())->method('getState')->with(7, 42)
            ->willReturn(['operation' => ['operation_id' => 42], 'source' => [], 'target' => [], 'changes' => []]);
        $block = $this->block($query, ['category_tree_id' => 7, 'operation_id' => 42, 'details' => 1]);
        $config = $block->getConfig();

        self::assertSame([[7, 10, null], [7, 1, 43]], $calls);
        self::assertSame(42, $config['selected_operation_id']);
        self::assertSame(42, $config['selected_operation']['operation_id']);
        self::assertSame(100, $config['operations'][0]['operation_id']);
        self::assertSame(100, $config['operations_pagination']['next_before_id']);
        self::assertTrue($config['open_details']);
        self::assertSame('ergonode/category_tree_mapping/edit?category_tree_id=7', $config['urls']['mapping']);
        self::assertSame($config, $block->getConfig());
    }

    public function testOperationAlreadyOnThePageDoesNotRequireAnotherSummaryQuery(): void
    {
        $query = $this->createMock(CategoryTreeHistoryQueryInterface::class);
        $query->expects(self::once())->method('getTrees')->willReturn($this->trees());
        $query->expects(self::once())->method('getOperationsPage')->with(7, 10)->willReturn($this->page(100));
        $query->expects(self::once())->method('getState')->with(7, 100)
            ->willReturn(['source' => [], 'target' => [], 'changes' => []]);

        $config = $this->block($query, ['category_tree_id' => 7, 'operation_id' => 100])->getConfig();

        self::assertSame(100, $config['selected_operation_id']);
        self::assertFalse($config['open_details']);
    }

    public function testAnOperationFromAnotherMappingDoesNotSelectItsNeighbour(): void
    {
        $query = $this->createMock(CategoryTreeHistoryQueryInterface::class);
        $query->expects(self::once())->method('getTrees')->willReturn($this->trees());
        $query->expects(self::exactly(2))->method('getOperationsPage')->willReturnOnConsecutiveCalls(
            $this->page(100),
            $this->page(41)
        );
        $query->expects(self::once())->method('getState')->with(7, 100)
            ->willReturn(['source' => [], 'target' => [], 'changes' => []]);

        $config = $this->block($query, ['category_tree_id' => 7, 'operation_id' => 42])->getConfig();

        self::assertSame(100, $config['selected_operation_id']);
    }

    public function testEmptyMappingsDoNotLoadHistory(): void
    {
        $query = $this->createMock(CategoryTreeHistoryQueryInterface::class);
        $query->expects(self::once())->method('getTrees')->willReturn([]);
        $query->expects(self::never())->method('getOperationsPage');
        $query->expects(self::never())->method('getState');

        $config = $this->block($query, ['operation_id' => 42])->getConfig();

        self::assertNull($config['selected_operation']);
        self::assertNull($config['state']);
    }

    private function block(CategoryTreeHistoryQueryInterface $query, array $params): Index
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
        $block = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest', 'getUrl'])
            ->getMock();
        $block->expects(self::atLeastOnce())->method('getRequest')->willReturn($request);
        $block->expects(self::atLeastOnce())->method('getUrl')->willReturnCallback(
            static fn (string $route, array $queryParams = []): string => $route . '?' . http_build_query($queryParams)
        );
        (new ReflectionProperty(Index::class, 'historyView'))->setValue($block, new HistoryView());
        (new ReflectionProperty(Index::class, 'historyQuery'))->setValue($block, $query);

        return $block;
    }

    private function trees(): array
    {
        return [['category_tree_id' => 7, 'tree_code' => 'default', 'root_category_id' => 2, 'is_active' => true]];
    }

    private function page(int $operationId): array
    {
        return [
            'items' => [['operation_id' => $operationId]],
            'total' => 30,
            'page_size' => 10,
            'has_more' => true,
            'next_before_id' => $operationId,
        ];
    }
}
