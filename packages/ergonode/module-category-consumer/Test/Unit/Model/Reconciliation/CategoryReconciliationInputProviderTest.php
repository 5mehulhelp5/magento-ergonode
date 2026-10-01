<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryMappingContextProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryReconciliationInputProviderTest extends TestCase
{
    public function testPreviewPreservesEffectiveLayoutAndExposesSnapshotLayout(): void
    {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['root_category_id' => 2, 'is_active' => true]);
        $refresh = $this->createStub(CategoryTreeRefreshServiceInterface::class);
        $refresh->method('refresh')->willReturn([]);
        $cache = $this->createStub(CategoryCacheProvider::class);
        $cache->method('getRows')->willReturn([[
            'code' => 'chairs',
            'parent_code' => 'furniture',
            'effective_parent_code' => 'living-room',
            'sort_order' => 1824,
            'effective_sort_order' => 3,
            'labels' => ['pl_PL' => 'Krzesła'],
        ]]);
        $mappingQuery = $this->createStub(CategoryMappingQuery::class);
        $mappingQuery->method('getMappingsByTreeId')->willReturn([]);
        $magentoProvider = $this->createStub(MagentoCategoryProvider::class);
        $magentoProvider->method('getCategories')->willReturn([]);
        $visibility = $this->createStub(MappingVisibilityProviderInterface::class);
        $visibility->method('getActiveMap')->willReturn([]);

        $input = (new CategoryReconciliationInputProvider(
            $treeQuery,
            $refresh,
            $cache,
            $magentoProvider,
            new CategoryMappingContextProvider($treeQuery, $cache, $mappingQuery, $magentoProvider, $visibility),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategoryCreationConfigurationProviderInterface::class)
        ))->get((new CategoryReconciliationRequest())->setCategoryTreeId(7));

        self::assertSame('living-room', $input['sources'][0]['parent_code']);
        self::assertSame(3, $input['sources'][0]['sort_order']);
        self::assertSame('furniture', $input['sources'][0]['source_parent_code']);
        self::assertSame(1824, $input['sources'][0]['source_sort_order']);
    }
}
