<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model;

use Ergonode\Category\Model\Provider\CategorySourceIssueProvider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;

use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\Config\RootCategoryScopeProvider;
use Ergonode\Category\Model\Config\Source\CategoryTreeOptions;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\CategoryAdminUi\Model\CategoryTreeContextProvider;

class CategoryTreeContextProviderTest extends TestCase
{
    public function testBuildsUserFacingTreeAndMagentoRootContext(): void
    {
        $categoryTreeQuery = $this->createStub(CategoryTreeQuery::class);
        $categoryTreeQuery->method('getList')->willReturn([[
            'category_tree_id' => 7,
            'tree_code' => 'tree-pl',
            'root_category_id' => 41,
            'sort_order' => 3,
            'is_active' => true,
            'remove_missing' => true,
        ]]);
        $treeOptions = $this->createStub(CategoryTreeOptions::class);
        $treeOptions->method('toOptionArray')->willReturn([
            ['value' => '', 'label' => 'Select category tree'],
            ['value' => 'tree-pl', 'label' => 'Polish categories'],
        ]);
        $rootCategoryScopeProvider = $this->createStub(RootCategoryScopeProvider::class);
        $rootCategoryScopeProvider->method('getRootCategories')->willReturn([[
            'root_category_id' => 41,
            'label' => 'PLN Root (#41) - PLN Store',
        ]]);

        $provider = new CategoryTreeContextProvider(
            $categoryTreeQuery,
            $treeOptions,
            $rootCategoryScopeProvider,
            $this->createStub(CategoryTreeSourceState::class),
            $this->createStub(CategorySourceIssueProvider::class)
        );

        self::assertSame(
            [[
                'category_tree_id' => 7,
                'tree_code' => 'tree-pl',
                'tree_label' => 'Polish categories',
                'root_category_id' => 41,
                'root_category_label' => 'PLN Root (#41) - PLN Store',
                'sort_order' => 3,
                'is_active' => true,
                'remove_missing' => true,
                'source_state' => [],
            ]],
            $provider->getList()
        );
    }
}
