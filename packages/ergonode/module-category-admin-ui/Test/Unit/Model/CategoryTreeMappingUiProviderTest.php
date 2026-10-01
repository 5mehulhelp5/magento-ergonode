<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Test\Unit\Model;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryAdminUi\Model\CategoryTreeContextProvider;
use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;

class CategoryTreeMappingUiProviderTest extends TestCase
{
    public function testProvidesScopedVisibilityAndCanonicalCodesForAutomaticMapping(): void
    {
        $categoryCacheProvider = $this->createMock(CategoryCacheProvider::class);
        $categoryCacheProvider->expects(self::once())->method('getRows')->with(7)->willReturn([[
            'code' => 'krzesla',
            'ergonode_category_id' => 'remote-krzesla',
            'label' => 'Krzesła',
            'effective_parent_code' => null,
            'parent_code' => null,
            'manual_parent_code' => null,
            'effective_sort_order' => 0,
            'sort_order' => 0,
            'manual_sort_order' => null,
            'magento_category_id' => null,
            'sync_status' => 'pending',
            'sync_message' => null,
        ]]);
        $magentoCategoryProvider = $this->createMock(MagentoCategoryProvider::class);
        $magentoCategoryProvider->expects(self::once())->method('getCategories')->with(2)->willReturn([
            2 => [
                'id' => 2,
                'parent_id' => 1,
                'label' => 'Default Category',
                'path' => '1/2',
                'level' => 1,
                'position' => 1,
                'url_key' => '',
            ],
            3 => [
                'id' => 3,
                'parent_id' => 2,
                'label' => 'Krzesła',
                'path' => '1/2/3',
                'level' => 2,
                'position' => 1,
                'url_key' => 'krzesla',
            ],
        ]);
        $visibilityProvider = $this->createMock(MappingVisibilityProviderInterface::class);
        $visibilityProvider->expects(self::exactly(2))
            ->method('getActiveMap')
            ->willReturnMap([
                ['category', 'ergo', ['krzesla'], '7', ['krzesla' => false]],
                ['category', 'magento', ['2', '3'], '7', ['2' => true, '3' => false]],
            ]);
        $categoryTreeContextProvider = $this->createTreeContextProvider();
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(7);

        $config = (new CategoryTreeMappingUiProvider(
            $categoryCacheProvider,
            $magentoCategoryProvider,
            $request,
            $categoryTreeContextProvider,
            $visibilityProvider,
        ))->getConfig();

        self::assertFalse($config['categories'][0]['active']);
        self::assertSame('remote-krzesla', $config['categories'][0]['ergonode_category_id']);
        self::assertArrayNotHasKey('manual_parent_code', $config['categories'][0]);
        self::assertArrayNotHasKey('manual_sort_order', $config['categories'][0]);
        self::assertArrayNotHasKey('has_sync_cursor', $config);
        self::assertArrayNotHasKey('synchronization_blockers', $config);
        self::assertArrayNotHasKey('sync_metadata', $config);
        self::assertTrue($config['magento_categories'][0]['active']);
        self::assertFalse($config['magento_categories'][1]['active']);
        self::assertArrayNotHasKey('sync_metadata', $config['category_trees'][0]);
    }

    public function testKeepsMissingCategoryTreeAsAnEmptyState(): void
    {
        $categoryCacheProvider = $this->createStub(CategoryCacheProvider::class);
        $categoryCacheProvider->method('getRows')->willReturn([]);
        $magentoCategoryProvider = $this->createMock(MagentoCategoryProvider::class);
        $magentoCategoryProvider->expects($this->never())->method('getCategories');
        $categoryTreeContextProvider = $this->createMock(CategoryTreeContextProvider::class);
        $categoryTreeContextProvider->method('getList')->willReturn([]);
        $categoryTreeContextProvider->expects($this->never())->method('getById');

        $config = $this->createProvider(
            $categoryCacheProvider,
            $magentoCategoryProvider,
            $categoryTreeContextProvider,
            0
        )->getConfig();

        self::assertSame('', $config['tree_code']);
        self::assertArrayNotHasKey('categoryTrees', $config);
        self::assertSame([], $config['category_trees']);
        self::assertSame([], $config['magento_categories']);
        self::assertArrayNotHasKey('has_sync_cursor', $config);
        self::assertArrayNotHasKey('sync_metadata', $config);
        self::assertSame(
            [
                'error' => null,
            ],
            $config['status']
        );
    }

    public function testSelectsFirstCategoryTreeWhenRequestDoesNotContainAnId(): void
    {
        $categoryCacheProvider = $this->createMock(CategoryCacheProvider::class);
        $categoryCacheProvider->expects(self::once())->method('getRows')->with(9)->willReturn([]);
        $magentoCategoryProvider = $this->createMock(MagentoCategoryProvider::class);
        $magentoCategoryProvider->expects(self::once())->method('getCategories')->with(4)->willReturn([]);
        $categoryTreeContextProvider = $this->createMock(CategoryTreeContextProvider::class);
        $categoryTree = [
            'category_tree_id' => 9,
            'root_category_id' => 4,
            'root_category_label' => 'Default Category (#4)',
            'tree_code' => 'default',
            'tree_label' => 'Default tree',
            'sort_order' => 0,
            'is_active' => true,
            'remove_missing' => false,
        ];
        $categoryTreeContextProvider->method('getList')->willReturn([$categoryTree]);
        $categoryTreeContextProvider->expects(self::once())
            ->method('getById')
            ->with([$categoryTree], 9)
            ->willReturn($categoryTree);

        $config = $this->createProvider(
            $categoryCacheProvider,
            $magentoCategoryProvider,
            $categoryTreeContextProvider,
            0
        )->getConfig();

        self::assertSame(9, $config['category_tree_id']);
        self::assertSame($categoryTree, $config['current_category_tree']);
        self::assertSame([$categoryTree], $config['category_trees']);
        self::assertSame([], $config['categories']);
        self::assertSame([], $config['magento_categories']);
        self::assertNull($config['status']['error']);
    }

    public function testMagentoCategoryFailureDoesNotSuggestChangingTreeConfiguration(): void
    {
        $categoryCacheProvider = $this->createStub(CategoryCacheProvider::class);
        $categoryCacheProvider->method('getRows')->willReturn([]);
        $magentoCategoryProvider = $this->createStub(MagentoCategoryProvider::class);
        $magentoCategoryProvider->method('getCategories')->willThrowException(
            new LocalizedException(new Phrase('Magento categories could not be loaded.'))
        );
        $categoryTreeContextProvider = $this->createTreeContextProvider();
        $categoryTree = $categoryTreeContextProvider->getList()[0];

        $config = $this->createProvider(
            $categoryCacheProvider,
            $magentoCategoryProvider,
            $categoryTreeContextProvider,
            7
        )->getConfig();

        self::assertSame('main', $config['tree_code']);
        self::assertSame($categoryTree, $config['current_category_tree']);
        self::assertSame([], $config['magento_categories']);
        self::assertSame(
            [
                'error' => 'Magento categories could not be loaded.',
            ],
            $config['status']
        );
    }

    public function testFreshConfigClearsBothProvidersBeforeReadingTheTrees(): void
    {
        $sourceFresh = false;
        $magentoFresh = false;
        $source = $this->createMock(CategoryCacheProvider::class);
        $source->expects(self::once())->method('clearCache')->willReturnCallback(
            static function () use (&$sourceFresh): void {
                $sourceFresh = true;
            }
        );
        $source->method('getRows')->willReturnCallback(static function () use (&$sourceFresh): array {
            self::assertTrue($sourceFresh);
            return [];
        });
        $magento = $this->createMock(MagentoCategoryProvider::class);
        $magento->expects(self::once())->method('clearCache')->willReturnCallback(
            static function () use (&$magentoFresh): void {
                $magentoFresh = true;
            }
        );
        $magento->method('getCategories')->willReturnCallback(static function () use (&$magentoFresh): array {
            self::assertTrue($magentoFresh);
            return [];
        });
        $config = $this->createProvider(
            $source,
            $magento,
            $this->createTreeContextProvider(),
            7
        )->getConfig(true);
        self::assertSame([], $config['categories']);
        self::assertSame([], $config['magento_categories']);
    }

    private function createProvider(
        CategoryCacheProvider $categoryCacheProvider,
        MagentoCategoryProvider $magentoCategoryProvider,
        CategoryTreeContextProvider $categoryTreeContextProvider,
        int $categoryTreeId
    ): CategoryTreeMappingUiProvider {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($categoryTreeId);
        return new CategoryTreeMappingUiProvider(
            $categoryCacheProvider,
            $magentoCategoryProvider,
            $request,
            $categoryTreeContextProvider,
            $this->createStub(MappingVisibilityProviderInterface::class),
        );
    }

    private function createTreeContextProvider(): CategoryTreeContextProvider
    {
        $categoryTree = [
            'category_tree_id' => 7,
            'root_category_id' => 2,
            'root_category_label' => 'Default Category (#2)',
            'tree_code' => 'main',
            'tree_label' => 'Main tree',
        ];
        $provider = $this->createStub(CategoryTreeContextProvider::class);
        $provider->method('getList')->willReturn([$categoryTree]);
        $provider->method('getById')->willReturn($categoryTree);

        return $provider;
    }
}
