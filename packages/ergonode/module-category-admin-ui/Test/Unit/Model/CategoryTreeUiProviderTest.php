<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Config\RootCategoryScopeProvider;
use Ergonode\CategoryAdminUi\Model\CategoryTreeUiProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreeUiProviderTest extends TestCase
{
    public function testIncludesFallbackForCategoryTreeOutsideCurrentStoreGroupRoots(): void
    {
        $categoryTreeQuery = $this->createStub(CategoryTreeQuery::class);
        $categoryTreeQuery->method('getList')->willReturn([
            ['root_category_id' => 2],
            ['root_category_id' => 99],
        ]);
        $rootCategoryScopeProvider = $this->createStub(RootCategoryScopeProvider::class);
        $rootCategoryScopeProvider->method('getRootCategories')->willReturn([
            [
                'root_category_id' => 2,
                'label' => 'Default Category (#2) - Main Store',
            ],
        ]);

        self::assertSame(
            [
                [
                    'value' => '2',
                    'label' => 'Default Category (#2) - Main Store',
                ],
                [
                    'value' => '99',
                    'label' => 'Root #99',
                ],
            ],
            (new CategoryTreeUiProvider(
                $categoryTreeQuery,
                $rootCategoryScopeProvider
            ))->getRootOptions()
        );
    }
}
