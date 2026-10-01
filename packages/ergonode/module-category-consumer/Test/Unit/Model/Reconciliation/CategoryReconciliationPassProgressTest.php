<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;

use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Ergonode\CategoryConsumer\Api\MappedCategoryAttributeSynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationExecutor;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationService;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationPaused;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class CategoryReconciliationPassProgressTest extends TestCase
{
    private array $links = [];
    private array $writes = [];
    private array $checkpoints = [];
    private array $moves = [];
    private int $created = 0;
    private ?string $pauseCode = null;
    private ?string $failureCode = null;
    private ?int $failedMoveId = null;
    private MagentoCategoryProvider $provider;

    public function test1871CategoriesAreWrittenOnceAcrossLevelsAndProgressNeverGoesBackwards(): void
    {
        $sources = [];
        for ($index = 0; $index < 1871; $index++) {
            $parent = $index < 17 ? null : 'category-' . ($index < 187
                ? intdiv($index - 17, 10) : 17 + intdiv($index - 187, 10));
            $sources[] = $this->source('category-' . $index, $parent, $index);
        }
        $service = $this->service($sources);
        $first = $service->execute($this->request());

        self::assertSame([], $first->getConflicts());
        self::assertSame(1871, $first->getStats()['created']);
        self::assertSame(0, $first->getStats()['unmatched']);
        self::assertSame(1871, $this->created);
        self::assertSame([], $this->moves);
        self::assertCount(1871, $this->links);
        // One recovery link after creation and one final status, without writes on later passes.
        self::assertSame([2], array_values(array_unique(array_count_values($this->writes))));
        $this->assertMonotonicProgress(1871);
        self::assertSame([0, 17, 187], $this->passStarts());
        foreach ($sources as $source) {
            $category = $this->provider->getCategory($this->links[$source['code']], 2);
            $parentId = $source['parent_code'] === null ? 2 : $this->links[$source['parent_code']];
            self::assertSame($parentId, $category['parent_id']);
        }

        $this->checkpoints = [];
        $this->writes = [];
        $second = $service->execute($this->request());
        self::assertSame(0, $second->getStats()['created']);
        self::assertSame(0, $second->getStats()['moved']);
        self::assertSame([1], array_values(array_unique(array_count_values($this->writes))));
        self::assertCount(1871, $this->writes);
        self::assertSame([0], $this->passStarts());
        $this->assertMonotonicProgress(1871);
    }

    public function testRemaps1871ExistingCategoriesWithoutLoadingUnusedEntityData(): void
    {
        $sources = [];
        $categories = [];
        for ($index = 0; $index < 1871; $index++) {
            $parentIndex = $index < 17 ? null : ($index < 187
                ? intdiv($index - 17, 10) : 17 + intdiv($index - 187, 10));
            $code = 'category-' . $index;
            $parent = $parentIndex === null ? null : 'category-' . $parentIndex;
            $sources[] = $this->source($code, $parent, $index);
            $categories[100 + $index] = $this->category(
                100 + $index,
                $parentIndex === null ? 2 : 100 + $parentIndex,
                $code,
                $index + 1
            );
        }
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::never())->method('load');
        $entitySync = $this->createMockForIntersectionOfInterfaces([
            CategoryEntitySynchronizerInterface::class, CategoryDataWorkProviderInterface::class,
        ]);
        $entitySync->expects(self::once())->method('hasWork')->willReturn(false);
        $entitySync->expects(self::never())->method('synchronize');
        $result = $this->service(
            $sources,
            $categories,
            new MappedCategoryAttributeSynchronizer($config, $loader, $entitySync)
        )->execute($this->request());

        self::assertSame([], $result->getConflicts());
        self::assertSame(0, $result->getStats()['created']);
        self::assertSame(0, $result->getStats()['unmatched']);
        self::assertSame([], $this->moves);
        self::assertCount(1871, $this->links);
        self::assertCount(1871, $this->writes);
        foreach ($sources as $index => $source) {
            self::assertSame(100 + $index, $this->links[$source['code']]);
        }
        $this->assertMonotonicProgress(1871);
    }

    public function testPreviouslyMappedChildCanMoveBelowANewParentWithoutRewritingItsOldParent(): void
    {
        $sources = [
            $this->source('old-parent', null, 0),
            $this->source('new-parent', null, 1),
            $this->source('kept', 'old-parent', 0),
            $this->source('moving', 'new-parent', 0),
            $this->source('leaf', 'moving', 0),
        ];
        $categories = [
            10 => $this->category(10, 2, 'old-parent', 1),
            11 => $this->category(11, 10, 'moving', 1),
            12 => $this->category(12, 10, 'kept', 2),
        ];
        $this->links = ['old-parent' => 10, 'moving' => 11, 'kept' => 12];
        $result = $this->service($sources, $categories)->execute($this->request());

        self::assertSame([], $result->getConflicts());
        self::assertSame(2, $result->getStats()['created']);
        self::assertSame(
            ['old-parent' => 1, 'kept' => 1, 'new-parent' => 2, 'moving' => 1, 'leaf' => 2],
            array_count_values($this->writes)
        );
        self::assertSame(0, $this->provider->getPreviousSiblingId(12, 2));
        self::assertSame($this->links['new-parent'], $this->provider->getCategory(11, 2)['parent_id']);
        self::assertSame(11, $this->provider->getCategory($this->links['leaf'], 2)['parent_id']);
        $this->assertMonotonicProgress(5);
    }

    public function testPauseKeepsTheParentMappingAndANewApplyCanFinishItsChildren(): void
    {
        $service = $this->service([
            $this->source('parent', null, 0),
            $this->source('child', 'parent', 0),
            $this->source('leaf', 'child', 0),
        ]);
        $this->pauseCode = 'child';
        try {
            $service->execute($this->request());
            self::fail('Expected a cooperative pause.');
        } catch (CategorySynchronizationPaused) {
            self::assertSame(['parent'], array_keys($this->links));
            self::assertSame(1, $this->created);
        }

        $this->pauseCode = null;
        $this->checkpoints = [];
        $result = $service->execute($this->request());
        self::assertSame([], $result->getConflicts());
        self::assertSame(2, $result->getStats()['created']);
        self::assertSame(3, $this->created);
        $this->assertMonotonicProgress(3);
    }

    public function testNewSiblingDoesNotStealMappedCategoryBeforeItMovesToAnotherBranch(): void
    {
        $sources = [
            $this->source('a', null, 0),
            $this->source('b', null, 1),
            array_replace($this->source('new', 'a', 0), ['label' => 'Child']),
            array_replace($this->source('owned', 'b', 0), ['label' => 'Child']),
        ];
        $this->links = ['a' => 10, 'b' => 11, 'owned' => 12];
        $result = $this->service($sources, [
            10 => $this->category(10, 2, 'a', 1),
            11 => $this->category(11, 2, 'b', 2),
            12 => $this->category(12, 10, 'Child', 1),
        ])->execute($this->request());

        self::assertSame([], $result->getConflicts());
        self::assertSame(1, $this->created);
        self::assertSame(12, $this->links['owned']);
        self::assertNotSame(12, $this->links['new']);
        self::assertSame(11, $this->provider->getCategory(12, 2)['parent_id']);
        self::assertSame(10, $this->provider->getCategory($this->links['new'], 2)['parent_id']);
    }

    public function testFailedChildDoesNotGetCountedOrAllowDeletionAndLaterPasses(): void
    {
        $this->failureCode = 'child';
        $service = $this->service([
            $this->source('parent', null, 0),
            $this->source('child', 'parent', 0),
            $this->source('leaf', 'child', 0),
        ]);
        $result = $service->execute($this->request());

        self::assertSame(1, $result->getStats()['created']);
        self::assertSame(['parent'], array_keys($this->links));
        self::assertStringContainsString('Creation failed.', implode(' ', $result->getConflicts()));
        self::assertSame(1, $this->checkpoints[array_key_last($this->checkpoints)]['processed']);
        self::assertSame([0, 1], $this->passStarts());
    }

    public function testMoveFailureKeepsTheNewCategoryLinkForTheNextApply(): void
    {
        $this->failedMoveId = 10001;
        $service = $this->service([$this->source('new', null, 0)], [
            10 => $this->category(10, 2, 'Unmanaged sibling', 1),
        ]);
        $failed = $service->execute($this->request());
        self::assertSame(1, $failed->getStats()['created']);
        self::assertSame(['new' => 10001], $this->links);
        self::assertSame(['new'], $this->writes);
        self::assertStringContainsString('Move failed.', implode(' ', $failed->getConflicts()));
        self::assertSame(0, $this->checkpoints[array_key_last($this->checkpoints)]['processed']);

        $this->failedMoveId = null;
        $this->checkpoints = [];
        $retry = $service->execute($this->request());
        self::assertSame([], $retry->getConflicts());
        self::assertSame(0, $retry->getStats()['created']);
        self::assertSame(1, $retry->getStats()['moved']);
        self::assertSame(1, $this->created);
        self::assertSame(0, $this->provider->getPreviousSiblingId(10001, 2));
        $this->assertMonotonicProgress(1);
    }

    private function service(
        array $sources,
        array $categories = [],
        ?MappedCategoryAttributeSynchronizerInterface $attributes = null
    ): CategoryReconciliationService {
        $this->provider = new MagentoCategoryProvider($this->createStub(CollectionFactory::class));
        $categories[2] = $this->category(2, 1, 'Root', 0);
        (new ReflectionProperty($this->provider, 'categoriesCache'))->setValue($this->provider, [2 => $categories]);
        $input = $this->createStub(CategoryReconciliationInputProvider::class);
        $input->method('get')->willReturnCallback(fn (): array => [
            'tree' => ['root_category_id' => 2, 'remove_missing' => true],
            'sources' => $sources,
            'magento' => $this->provider->getCategories(2),
            'database_mappings' => $this->links,
        ]);
        $creator = $this->createStub(CategoryCreationService::class);
        $creator->method('create')->willReturnCallback(function (string $code, string $label, int $parentId): array {
            if ($code === $this->failureCode) {
                throw new LocalizedException(__('Creation failed.'));
            }
            $position = 0;
            foreach ($this->provider->getCategories(2) as $category) {
                if ($category['parent_id'] === $parentId) {
                    $position = max($position, $category['position']);
                }
            }
            $this->created++;
            return $this->category(10000 + $this->created, $parentId, $label, $position + 1);
        });
        $mapping = $this->createStub(CategoryMappingWriter::class);
        $mapping->method('updateMagentoLink')->willReturnCallback(function (int $tree, string $code, int $id): void {
            $this->links[$code] = $id;
            $this->writes[] = $code;
        });
        $position = $this->createStub(CategoryPositionWriterInterface::class);
        $position->method('move')->willReturnCallback(function (int $id, int $parent, int $previous): void {
            if ($id === $this->failedMoveId) {
                throw new LocalizedException(__('Move failed.'));
            }
            $this->moves[] = [$id, $parent, $previous];
        });
        if ($attributes === null) {
            $attributes = $this->createMock(MappedCategoryAttributeSynchronizerInterface::class);
            $attributes->expects(self::never())->method('synchronize');
        }
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('checkpoint')->willReturnCallback(
            function (string $stage, int $processed, ?int $total, string $item = ''): void {
                $this->checkpoints[] = compact('stage', 'processed', 'total', 'item');
                if ($stage === 'creating_category' && $item === $this->pauseCode) {
                    throw new CategorySynchronizationPaused();
                }
            }
        );
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        return new CategoryReconciliationService(
            $input,
            new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver()),
            new CategoryReconciliationExecutor(
                $this->provider,
                $mapping,
                $creator,
                $position,
                $attributes,
                new ChangeReport(new Json()),
                $progress
            ),
            $this->provider,
            new CategorySynchronizationLock($lock),
            $this->createStub(CategorySourceAvailability::class),
            $this->cacheInvalidator()
        );
    }

    private function source(string $code, ?string $parent, int $position): array
    {
        return [
            'code' => $code, 'label' => $code, 'parent_code' => $parent, 'sort_order' => $position, 'active' => true,
        ];
    }

    private function category(int $id, int $parent, string $label, int $position): array
    {
        return [
            'id' => $id, 'parent_id' => $parent, 'label' => $label, 'position' => $position,
            'path' => '1/2/' . $id, 'level' => 2, 'url_key' => $label, 'active' => true,
        ];
    }

    private function request(): CategoryReconciliationRequest
    {
        return (new CategoryReconciliationRequest())->setCategoryTreeId(7)->setMode('apply');
    }

    private function assertMonotonicProgress(int $total): void
    {
        $previous = 0;
        foreach ($this->checkpoints as $checkpoint) {
            self::assertGreaterThanOrEqual($previous, $checkpoint['processed']);
            self::assertLessThanOrEqual($total, $checkpoint['processed']);
            self::assertSame($total, $checkpoint['total']);
            $previous = $checkpoint['processed'];
        }
        self::assertSame($total, $previous);
    }

    private function passStarts(): array
    {
        // Start and finish checkpoints have no item; each pass contributes that pair.
        $boundaries = array_values(array_filter(
            $this->checkpoints,
            static fn (array $item): bool => $item['stage'] === 'applying_tree' && $item['item'] === ''
        ));
        return array_column(
            array_filter($boundaries, static fn (int $key): bool => $key % 2 === 0, ARRAY_FILTER_USE_KEY),
            'processed'
        );
    }
    private function cacheInvalidator(): CategoryCacheInvalidator
    {
        $invalidator = $this->createStub(CategoryCacheInvalidator::class);
        $invalidator->method('defer')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        return $invalidator;
    }
}
