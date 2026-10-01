<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Unit\Model\Navigation;

use Ergonode\CategoryAttributeConsumerAdminUi\Model\Navigation\CategoryAttributeNavigationItemProvider;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;

class CategoryAttributeNavigationItemProviderTest extends TestCase
{
    public function testReturnsNoItemWithoutCategoryAttributePermission(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())
            ->method('isAllowed')
            ->with('Ergonode_CategoryConsumer::category_attribute_mapping')
            ->willReturn(false);
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->expects(self::never())->method('getUrl');

        self::assertSame([], (new CategoryAttributeNavigationItemProvider(
            $authorization,
            $urlBuilder
        ))->getItems('categories'));
    }

    public function testReturnsOnlyAttributes(): void
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->expects(self::once())
            ->method('getUrl')
            ->willReturnCallback(static fn (string $route): string => '/admin/' . $route);

        $items = (new CategoryAttributeNavigationItemProvider(
            $authorization,
            $urlBuilder
        ))->getItems(CategoryAttributeNavigationItemProvider::ATTRIBUTE_SECTION_CODE);

        self::assertCount(1, $items);
        self::assertSame('attribution-pen', $items[0]['icon']);
        self::assertSame('Attributes', (string)$items[0]['label']);
        self::assertSame('/admin/ergonode/category_attribute/index', $items[0]['url']);
        self::assertTrue($items[0]['is_current']);
    }

    public function testStandaloneOptionsDoesNotAddNavigationDestination(): void
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);
        $urlBuilder = $this->createStub(UrlInterface::class);

        $items = (new CategoryAttributeNavigationItemProvider(
            $authorization,
            $urlBuilder
        ))->getItems(CategoryAttributeNavigationItemProvider::OPTION_SECTION_CODE);

        self::assertFalse($items[0]['is_current']);
        self::assertCount(1, $items);
    }
}
