<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model\Navigation;

use Ergonode\CategoryAdminUi\Model\Navigation\CategoryTreeNavigationItemProvider;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryTreeNavigationItemProviderTest extends TestCase
{
    private AuthorizationInterface&MockObject $authorization;
    private UrlInterface&MockObject $urlBuilder;
    private CategoryTreeNavigationItemProvider $provider;

    protected function setUp(): void
    {
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->provider = new CategoryTreeNavigationItemProvider(
            $this->authorization,
            $this->urlBuilder
        );
    }

    public function testReturnsNoItemsWithoutCategoryTreePermission(): void
    {
        $this->authorization->expects(self::once())
            ->method('isAllowed')
            ->with('Ergonode_CategoryConsumer::category_tree_mapping')
            ->willReturn(false);
        $this->urlBuilder->expects(self::never())->method('getUrl');

        self::assertSame([], $this->provider->getItems(CategoryTreeNavigationItemProvider::SECTION_CODE));
    }

    public function testReturnsCurrentCategoryTreeItem(): void
    {
        $this->authorization->expects(self::once())
            ->method('isAllowed')
            ->with('Ergonode_CategoryConsumer::category_tree_mapping')
            ->willReturn(true);
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('ergonode/category_tree_mapping/edit')
            ->willReturn('https://example.test/admin/ergonode/category_tree_mapping/edit');

        $items = $this->provider->getItems(CategoryTreeNavigationItemProvider::SECTION_CODE);

        self::assertCount(1, $items);
        self::assertSame('list-tree', $items[0]['icon']);
        self::assertSame('Tree', (string)$items[0]['label']);
        self::assertSame(
            'https://example.test/admin/ergonode/category_tree_mapping/edit',
            $items[0]['url']
        );
        self::assertTrue($items[0]['is_current']);
    }
}
