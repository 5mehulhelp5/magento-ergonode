<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryAutoMapper;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryMappingContextProvider;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategoryAutoMapperTest extends TestCase
{
    public function testSuggestUsesDraftAndSnapshotWithoutWriting(): void
    {
        $tree = ['is_active' => true, 'root_category_id' => 2];
        $treeQuery = $this->createMock(CategoryTreeQuery::class);
        $treeQuery->expects(self::once())->method('getById')->with(7)->willReturn($tree);
        $sourceState = $this->createMock(CategoryTreeSourceState::class);
        $sourceState->expects(self::once())->method('assertCanUseSnapshot')->with(7);
        $sourceCache = $this->createMock(CategoryCacheProvider::class);
        $sourceCache->expects(self::once())->method('clearCache');
        $magentoProvider = $this->createMock(MagentoCategoryProvider::class);
        $magentoProvider->expects(self::once())->method('clearCache');
        $context = $this->createMock(CategoryMappingContextProvider::class);
        $draftVisibility = [['source' => 'ergo', 'identifier' => 'chairs', 'active' => true]];
        $context->expects(self::once())->method('get')->with(7, $draftVisibility, $tree)->willReturn([
            'tree' => $tree,
            'sources' => [[
                'code' => 'chairs', 'parent_code' => null, 'label' => 'Chairs',
                'sort_order' => 0, 'active' => true,
            ]],
            'magento' => [
                2 => ['id' => 2, 'parent_id' => 1, 'label' => 'Root', 'active' => true],
                10 => ['id' => 10, 'parent_id' => 2, 'label' => 'Chairs', 'active' => true],
            ],
            'database_mappings' => [],
        ]);
        $lock = $this->createMock(CategorySynchronizationLock::class);
        $lock->expects(self::once())->method('execute')->willReturnCallback(
            static fn (callable $operation): array => $operation()
        );

        $result = (new CategoryAutoMapper(
            $treeQuery,
            $sourceState,
            $sourceCache,
            $magentoProvider,
            $context,
            new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver()),
            $lock
        ))->suggest(7, [['ergonode_code' => 'chairs', 'magento_category_id' => 10]], $draftVisibility);

        self::assertSame(10, $result['categories'][0]['magento_category_id']);
        self::assertSame('draft', $result['categories'][0]['mapping_source']);
        self::assertSame(1, $result['stats']['draft']);
        self::assertTrue($result['magento_categories'][1]['consumed']);
        self::assertSame([], $result['conflicts']);
    }

    public function testInactiveTreeDoesNotReadSnapshot(): void
    {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['is_active' => false]);
        $context = $this->createMock(CategoryMappingContextProvider::class);
        $context->expects(self::never())->method('get');
        $lock = $this->createStub(CategorySynchronizationLock::class);
        $lock->method('execute')->willReturnCallback(static fn (callable $operation): array => $operation());

        $mapper = new CategoryAutoMapper(
            $treeQuery,
            $this->createStub(CategoryTreeSourceState::class),
            $this->createStub(CategoryCacheProvider::class),
            $this->createStub(MagentoCategoryProvider::class),
            $context,
            new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver()),
            $lock
        );

        $this->expectException(LocalizedException::class);
        $mapper->suggest(7);
    }
}
