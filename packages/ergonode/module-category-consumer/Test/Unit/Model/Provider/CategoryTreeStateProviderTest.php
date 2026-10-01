<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Provider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\CategoryConsumer\Model\Provider\CategoryTreeStateProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryTreeStateProviderTest extends TestCase
{
    public function testProvidesCanonicalSourceAndMagentoState(): void
    {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn([
            'category_tree_id' => 7,
            'tree_code' => 'main-pl',
            'root_category_id' => 2,
            'is_active' => true,
        ]);
        $categoryCache = $this->createMock(CategoryCacheProvider::class);
        $sourceCacheCleared = false;
        $categoryCache->expects(self::once())->method('clearCache')->willReturnCallback(
            static function () use (&$sourceCacheCleared): void {
                $sourceCacheCleared = true;
            }
        );
        $sourceRows = [[
            'code' => 'chairs',
            'label' => 'Chairs',
            'effective_parent_code' => 'living-room',
            'parent_code' => 'source-parent',
            'effective_sort_order' => 3,
            'sort_order' => 1,
            'magento_category_id' => 12,
        ]];
        $categoryCache->method('getRows')->willReturnCallback(
            static function () use (&$sourceCacheCleared, $sourceRows): array {
                self::assertTrue($sourceCacheCleared, 'Source state must bypass previously cached mappings.');

                return $sourceRows;
            }
        );
        $magentoCategories = $this->createMock(MagentoCategoryProvider::class);
        $targetCacheCleared = false;
        $magentoCategories->expects(self::once())->method('clearCache')->willReturnCallback(
            static function () use (&$targetCacheCleared): void {
                $targetCacheCleared = true;
            }
        );
        $targetRows = [
            2 => [
                'id' => 2,
                'parent_id' => 1,
                'label' => 'Default Category',
                'path' => '1/2',
                'level' => 1,
                'position' => 0,
                'url_key' => '',
            ],
            12 => [
                'id' => 12,
                'parent_id' => 2,
                'label' => 'Magento Chairs',
                'path' => '1/2/12',
                'level' => 2,
                'position' => 4,
                'url_key' => 'chairs',
            ],
        ];
        $magentoCategories->method('getCategories')->willReturnCallback(
            static function () use (&$targetCacheCleared, $targetRows): array {
                self::assertTrue($targetCacheCleared, 'Target state must bypass previously cached Magento categories.');

                return $targetRows;
            }
        );
        $visibility = $this->createStub(MappingVisibilityProviderInterface::class);
        $visibility->method('getActiveMap')->willReturnCallback(
            static fn (
                string $_entityType,
                string $source,
                array $identifiers,
                string $_parentIdentifier = ''
            ): array => array_fill_keys(
                $identifiers,
                $source === 'ergo'
            )
        );

        $state = (new CategoryTreeStateProvider(
            $treeQuery,
            $categoryCache,
            $magentoCategories,
            $visibility
        ))->getState(7);

        self::assertSame('Default Category', $state['tree']['root_label']);
        self::assertSame('living-room', $state['source'][0]['parent_identifier']);
        self::assertSame('source-parent', $state['source'][0]['source_parent_identifier']);
        self::assertSame('Magento Chairs', $state['source'][0]['magento_label']);
        self::assertTrue($state['source'][0]['active']);
        self::assertSame('chairs', $state['target'][1]['category_code']);
        self::assertFalse($state['target'][1]['active']);
    }
}
