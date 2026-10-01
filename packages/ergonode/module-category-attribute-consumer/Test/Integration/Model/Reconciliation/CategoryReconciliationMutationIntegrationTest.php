<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Reconciliation;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryDeletionExecutor;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationExecutor;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryReconciliationMutationIntegrationTest extends TestCase
{
    #[DataFixture(CategoryFixture::class, ['name' => 'Move parent A %uniqid%'], as: 'parent_a')]
    #[DataFixture(CategoryFixture::class, ['name' => 'Move parent B %uniqid%'], as: 'parent_b')]
    #[DataFixture(
        CategoryFixture::class,
        ['name' => 'Moving child %uniqid%', 'parent_id' => '$parent_a.id$'],
        as: 'moving_child'
    )]
    public function testMoveIsAppliedParentFirstAndSecondPassIsNoOp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $provider = $objectManager->get(MagentoCategoryProvider::class);
        $provider->clearCache();
        $parentA = $this->fixtureId('parent_a');
        $parentB = $this->fixtureId('parent_b');
        $child = $this->fixtureId('moving_child');
        $categories = $provider->getCategories(2);

        $mappingWriter = $this->createStub(CategoryMappingWriter::class);
        $creator = $this->createMock(CategoryCreationService::class);
        $creator->expects($this->never())->method('create');
        $executor = new CategoryReconciliationExecutor(
            $provider,
            $mappingWriter,
            $creator,
            $objectManager->get(CategoryPositionWriterInterface::class),
            $this->createStub(MappedCategoryAttributeSynchronizer::class),
            $objectManager->get(ChangeReport::class),
            $objectManager->get(CategorySynchronizationProgress::class)
        );
        $topLevel = [$parentA, $parentB];
        usort(
            $topLevel,
            static fn (int $first, int $second): int =>
                (int)$categories[$first]['position'] <=> (int)$categories[$second]['position']
        );
        $sources = [];
        $assignments = [];
        foreach ($topLevel as $sortOrder => $categoryId) {
            $code = $categoryId === $parentA ? 'parent-a' : 'parent-b';
            $sources[] = [
                'code' => $code,
                'parent_code' => null,
                'label' => (string)$categories[$categoryId]['label'],
                'labels' => [],
                'sort_order' => $sortOrder,
            ];
            $assignments[$code] = [
                'magento_category_id' => $categoryId,
                'source' => 'database',
                'expected_parent_id' => 2,
            ];
        }
        $sources[] = [
            'code' => 'moving-child',
            'parent_code' => 'parent-b',
            'label' => (string)$categories[$child]['label'],
            'labels' => [],
            'sort_order' => 0,
        ];
        $assignments['moving-child'] = [
            'magento_category_id' => $child,
            'source' => 'database',
            'expected_parent_id' => $parentB,
        ];

        $first = $executor->executePass(7, 2, $sources, ['assignments' => $assignments]);
        $second = $executor->executePass(7, 2, $sources, ['assignments' => $assignments]);

        self::assertGreaterThanOrEqual(1, $first['moved']);
        self::assertSame(0, $second['moved']);
        self::assertSame(
            $parentB,
            (int)$objectManager->get(CategoryRepositoryInterface::class)->get($child, 0, true)->getParentId()
        );
    }

    #[DataFixture(CategoryFixture::class, ['name' => 'Delete parent %uniqid%'], as: 'delete_parent')]
    #[DataFixture(
        CategoryFixture::class,
        ['name' => 'Delete child %uniqid%', 'parent_id' => '$delete_parent.id$'],
        as: 'delete_child'
    )]
    public function testDeleteCandidatesAreResolvedAndDeletedDeepestFirst(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $provider = $objectManager->get(MagentoCategoryProvider::class);
        $provider->clearCache();
        $parent = $this->fixtureId('delete_parent');
        $child = $this->fixtureId('delete_child');
        $resolution = (new CategoryIdentityResolver(
            new CategoryNameNormalizer(),
            new CategoryDeletionCandidateResolver()
        ))->resolve(
            2,
            [],
            $provider->getCategories(2),
            ['delete-parent' => $parent, 'delete-child' => $child]
        );

        self::assertSame([$child, $parent], $resolution['delete_candidates']);

        $mappingWriter = $this->createMock(CategoryMappingWriter::class);
        $mappingWriter->expects($this->exactly(2))->method('markMagentoCategoryDeletedById');
        $deleted = (new CategoryDeletionExecutor(
            $objectManager->get(CategoryRepositoryInterface::class),
            $mappingWriter,
            $provider,
            $objectManager->get(State::class),
            $objectManager->get(Registry::class),
            $objectManager->get(CategorySynchronizationProgress::class)
        ))->execute(7, $resolution['delete_candidates']);

        self::assertSame(2, $deleted);
        $repository = $objectManager->get(CategoryRepositoryInterface::class);
        foreach ([$child, $parent] as $categoryId) {
            try {
                $repository->get($categoryId, 0, true);
                self::fail('Deleted category is still available.');
            } catch (NoSuchEntityException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function fixtureId(string $alias): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($alias);
        self::assertNotNull($fixture);

        return (int)$fixture->getId();
    }
}
