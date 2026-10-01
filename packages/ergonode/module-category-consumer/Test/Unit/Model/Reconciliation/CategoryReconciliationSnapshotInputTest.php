<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryMappingContextProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CategoryReconciliationSnapshotInputTest extends TestCase
{
    private CategoryTreeRefreshServiceInterface&MockObject $refresh;
    private CategoryCacheProvider&MockObject $cache;
    private MagentoCategoryProvider&MockObject $magento;
    private CategoryTreeQuery&Stub $tree;
    private CategoryReconciliationInputProvider $provider;

    private CategoryCreationConfigurationProviderInterface&Stub $creation;

    protected function setUp(): void
    {
        $this->refresh = $this->createMock(CategoryTreeRefreshServiceInterface::class);
        $this->cache = $this->createMock(CategoryCacheProvider::class);
        $this->magento = $this->createMock(MagentoCategoryProvider::class);
        $this->tree = $this->createStub(CategoryTreeQuery::class);
        $mappings = $this->createStub(CategoryMappingQuery::class);
        $mappings->method('getMappingsByTreeId')->willReturn(['chairs' => 12]);
        $visibility = $this->createStub(MappingVisibilityProviderInterface::class);
        $visibility->method('getActiveMap')->willReturn([]);
        $this->creation = $this->createStub(CategoryCreationConfigurationProviderInterface::class);
        $this->provider = new CategoryReconciliationInputProvider(
            $this->tree,
            $this->refresh,
            $this->cache,
            $this->magento,
            new CategoryMappingContextProvider($this->tree, $this->cache, $mappings, $this->magento, $visibility),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->creation
        );
    }

    public function testApplyRejectsInvalidCreationConfigurationBeforeRemoteRefresh(): void
    {
        $this->tree->method('getById')->willReturn(['is_active' => true]);
        $this->creation->method('get')->willThrowException(
            new LocalizedException(__('Missing is_active mapping.'))
        );
        $this->refresh->expects(self::never())->method('refresh');
        $this->cache->expects(self::never())->method('getRows');
        $this->magento->expects(self::never())->method('getCategories');
        $this->expectExceptionMessage('Missing is_active mapping.');
        $this->provider->get((new CategoryReconciliationRequest())
            ->setCategoryTreeId(7)->setMode(CategoryReconciliationRequestInterface::MODE_APPLY));
    }

    public function testPreviewUsesSnapshotAndCurrentMagentoDataWithoutRemoteRefresh(): void
    {
        $this->tree->method('getById')->willReturn([
            'root_category_id' => 2, 'tree_code' => 'test-tree', 'is_active' => true,
        ]);
        $this->refresh->expects(self::never())->method('refresh');
        $this->cache->expects(self::once())->method('clearCache');
        $this->magento->expects(self::once())->method('clearCache');
        $this->cache->expects(self::once())->method('getRows')->with(7)->willReturn([[
            'code' => 'chairs',
            'parent_code' => null,
            'sort_order' => 0,
            'labels' => ['pl_PL' => 'Krzesła'],
            'label' => 'Krzesła',
        ]]);
        $this->magento->expects(self::once())->method('getCategories')->with(2)->willReturn([
            12 => ['id' => 12, 'label' => 'Krzesła'],
        ]);
        $request = (new CategoryReconciliationRequest())->setCategoryTreeId(7)->setDraftVisibility([
            ['source' => 'ergo', 'identifier' => 'chairs', 'active' => false],
            ['source' => 'magento', 'identifier' => '12', 'active' => false],
        ]);

        $input = $this->provider->get($request);

        self::assertSame('chairs', $input['sources'][0]['code']);
        self::assertFalse($input['sources'][0]['active']);
        self::assertFalse($input['magento'][12]['active']);
        self::assertSame(['chairs' => 12], $input['database_mappings']);
        self::assertSame([], $input['fresh']);
    }

    public function testEmptyPreviewDoesNotDownloadOrPopulateTheSnapshot(): void
    {
        $this->tree->method('getById')->willReturn([
            'root_category_id' => 2, 'tree_code' => 'test-tree', 'is_active' => true,
        ]);
        $this->refresh->expects(self::never())->method('refresh');
        $this->cache->expects(self::once())->method('getRows')->willReturn([]);
        $this->magento->expects(self::once())->method('getCategories')->willReturn([]);

        self::assertSame([], $this->provider->get(
            (new CategoryReconciliationRequest())->setCategoryTreeId(7)
        )['sources']);
    }

    public function testApplyRefreshesTheCompleteTreeBeforeReadingSnapshot(): void
    {
        $this->tree->method('getById')->willReturn([
            'root_category_id' => 2, 'tree_code' => 'test-tree', 'is_active' => true,
        ]);
        $refreshed = false;
        $this->refresh->expects(self::once())->method('refresh')->with(7)->willReturnCallback(
            static function () use (&$refreshed): array {
                $refreshed = true;
                return ['complete' => true, 'pages' => 3];
            }
        );
        $this->cache->expects(self::once())->method('getRows')->with(7)->willReturnCallback(
            static function () use (&$refreshed): array {
                self::assertTrue($refreshed);
                return [];
            }
        );
        $this->magento->expects(self::once())->method('getCategories')->willReturn([]);
        $request = (new CategoryReconciliationRequest())->setCategoryTreeId(7)
            ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY);

        self::assertSame(['complete' => true, 'pages' => 3], $this->provider->get($request)['fresh']);
    }

    public function testApplyDoesNotFallBackToSnapshotAfterRefreshFailure(): void
    {
        $this->tree->method('getById')->willReturn([
            'root_category_id' => 2, 'tree_code' => 'test-tree', 'is_active' => true,
        ]);
        $this->refresh->expects(self::once())->method('refresh')
            ->willThrowException(new LocalizedException(__('Refresh failed.')));
        $this->cache->expects(self::never())->method('getRows');
        $this->magento->expects(self::never())->method('getCategories');
        $this->magento->expects(self::never())->method('getCategories');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Refresh failed.');

        $this->provider->get((new CategoryReconciliationRequest())->setCategoryTreeId(7)
            ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY));
    }

    public function testInactiveTreeStillRejectsPreviewWithoutFetchingData(): void
    {
        $this->tree->method('getById')->willReturn([
            'root_category_id' => 2, 'tree_code' => 'test-tree', 'is_active' => false,
        ]);
        $this->refresh->expects(self::never())->method('refresh');
        $this->cache->expects(self::never())->method('getRows');
        $this->magento->expects(self::never())->method('getCategories');
        $this->magento->expects(self::never())->method('getCategories');
        $this->expectException(LocalizedException::class);

        $this->provider->get((new CategoryReconciliationRequest())->setCategoryTreeId(7));
    }
}
