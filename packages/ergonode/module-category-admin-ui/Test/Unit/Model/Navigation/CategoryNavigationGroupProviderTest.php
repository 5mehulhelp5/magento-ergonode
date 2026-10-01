<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model\Navigation;

use Ergonode\CategoryAdminUi\Api\CategoryNavigationItemProviderInterface;
use Ergonode\CategoryAdminUi\Model\Navigation\CategoryNavigationGroupProvider;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

class CategoryNavigationGroupProviderTest extends TestCase
{
    public function testReturnsNoGroupWithoutAuthorizedItems(): void
    {
        $itemProvider = $this->createStub(CategoryNavigationItemProviderInterface::class);
        $itemProvider->method('getItems')->willReturn([]);

        $provider = new CategoryNavigationGroupProvider([$itemProvider]);

        self::assertNull($provider->getGroup('categories'));
    }

    public function testBuildsCategoryGroupFromContributedItems(): void
    {
        $treeProvider = $this->createMock(CategoryNavigationItemProviderInterface::class);
        $treeProvider->expects(self::once())->method('getItems')->with('category_attributes')->willReturn([[
            'icon' => 'list-tree',
            'label' => new Phrase('Tree'),
            'url' => '/category-tree',
            'is_current' => false,
        ]]);
        $attributeProvider = $this->createMock(CategoryNavigationItemProviderInterface::class);
        $attributeProvider->expects(self::once())->method('getItems')->with('category_attributes')->willReturn([[
            'icon' => 'attribution-pen',
            'label' => new Phrase('Attributes'),
            'url' => '/category-attributes',
            'is_current' => true,
        ]]);

        $provider = new CategoryNavigationGroupProvider(
            [$treeProvider, $attributeProvider],
            ['categories', 'category_attributes']
        );
        $group = $provider->getGroup('category_attributes');

        self::assertNotNull($group);
        self::assertSame('Categories', (string)$group['label']);
        self::assertSame('/category-tree', $group['url']);
        self::assertSame(['categories', 'category_attributes'], $group['section_codes']);
        self::assertCount(2, $group['items']);
        self::assertTrue($group['items'][1]['is_current']);
    }
}
