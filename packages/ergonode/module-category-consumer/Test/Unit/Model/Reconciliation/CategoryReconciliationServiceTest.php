<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;

use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;

use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationExecutor;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationService;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryReconciliationServiceTest extends TestCase
{
    public function testTwoPreviewsAreIdempotentAndNeverMutateMagento(): void
    {
        $input = $this->input(false);
        $inputProvider = $this->createMock(CategoryReconciliationInputProvider::class);
        $inputProvider->expects($this->exactly(2))->method('get')->willReturn($input);
        $executor = $this->createMock(CategoryReconciliationExecutor::class);
        $executor->expects($this->never())->method('executePass');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->exactly(2))->method('lock')->willReturn(true);
        $lockManager->expects($this->exactly(2))->method('unlock');
        $service = new CategoryReconciliationService(
            $inputProvider,
            $this->identityResolver(),
            $executor,
            $this->createStub(MagentoCategoryProvider::class),
            new CategorySynchronizationLock($lockManager),
            $this->createStub(CategorySourceAvailability::class),
            $this->cacheInvalidator()
        );
        $request = (new CategoryReconciliationRequest())
            ->setCategoryTreeId(7)
            ->setMode(CategoryReconciliationRequestInterface::MODE_PREVIEW);

        $first = $service->execute($request);
        $second = $service->execute($request);

        self::assertSame($first->getCategories(), $second->getCategories());
        self::assertSame($first->getMagentoCategories(), $second->getMagentoCategories());
        self::assertSame($first->getStats(), $second->getStats());
        self::assertSame(10, $first->getCategories()[0]['magento_category_id']);
    }

    public function testApplyPreservesCategoriesEvenWhenLegacyRemoveMissingIsEnabled(): void
    {
        $input = $this->input(true);
        $inputProvider = $this->createStub(CategoryReconciliationInputProvider::class);
        $inputProvider->method('get')->willReturn($input);
        $executor = $this->createMock(CategoryReconciliationExecutor::class);
        $executor->expects($this->once())->method('executePass')->willReturn([
            'created' => 0,
            'moved' => 0,
            'updated' => 0,
            'mappings' => ['chairs' => 10],
            'errors' => [],
        ]);
        $magentoProvider = $this->createStub(MagentoCategoryProvider::class);
        $magentoProvider->method('getCategories')->willReturn($input['magento']);
        $service = new CategoryReconciliationService(
            $inputProvider,
            $this->identityResolver(),
            $executor,
            $magentoProvider,
            $this->lock(),
            $this->createStub(CategorySourceAvailability::class),
            $this->cacheInvalidator()
        );

        $result = $service->execute(
            (new CategoryReconciliationRequest())
                ->setCategoryTreeId(7)
                ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY)
        );

        self::assertSame(0, $result->getStats()['deleted']);
        self::assertSame([11], $result->getDeleteCandidates());
    }

    public function testApplyDoesNotDeleteWhenRemoveMissingIsDisabled(): void
    {
        $input = $this->input(false);
        $inputProvider = $this->createStub(CategoryReconciliationInputProvider::class);
        $inputProvider->method('get')->willReturn($input);
        $executor = $this->createStub(CategoryReconciliationExecutor::class);
        $executor->method('executePass')->willReturn([
            'created' => 0,
            'moved' => 0,
            'updated' => 0,
            'mappings' => ['chairs' => 10],
            'errors' => [],
        ]);
        $magentoProvider = $this->createStub(MagentoCategoryProvider::class);
        $magentoProvider->method('getCategories')->willReturn($input['magento']);
        $service = new CategoryReconciliationService(
            $inputProvider,
            $this->identityResolver(),
            $executor,
            $magentoProvider,
            $this->lock(),
            $this->createStub(CategorySourceAvailability::class),
            $this->cacheInvalidator()
        );

        $result = $service->execute(
            (new CategoryReconciliationRequest())
                ->setCategoryTreeId(7)
                ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY)
        );

        self::assertSame(0, $result->getStats()['deleted']);
        self::assertSame([11], $result->getDeleteCandidates());
    }

    public function testApplyDoesNotDeleteAfterMutationError(): void
    {
        $input = $this->input(true);
        $inputProvider = $this->createStub(CategoryReconciliationInputProvider::class);
        $inputProvider->method('get')->willReturn($input);
        $executor = $this->createStub(CategoryReconciliationExecutor::class);
        $executor->method('executePass')->willReturn([
            'created' => 0,
            'moved' => 0,
            'updated' => 0,
            'mappings' => [],
            'errors' => ['Name write failed.'],
        ]);
        $magentoProvider = $this->createStub(MagentoCategoryProvider::class);
        $magentoProvider->method('getCategories')->willReturn($input['magento']);
        $service = new CategoryReconciliationService(
            $inputProvider,
            $this->identityResolver(),
            $executor,
            $magentoProvider,
            $this->lock(),
            $this->createStub(CategorySourceAvailability::class),
            $this->cacheInvalidator()
        );

        $result = $service->execute(
            (new CategoryReconciliationRequest())
                ->setCategoryTreeId(7)
                ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY)
        );

        self::assertSame(0, $result->getStats()['deleted']);
        self::assertContains('Name write failed.', $result->getConflicts());
    }

    /** @return array<string, mixed> */
    private function input(bool $removeMissing): array
    {
        return [
            'tree' => [
                'category_tree_id' => 7,
                'root_category_id' => 2,
                'remove_missing' => $removeMissing,
            ],
            'sources' => [[
                'code' => 'chairs',
                'parent_code' => null,
                'label' => 'Chairs',
                'labels' => ['en_US' => 'Chairs'],
                'sort_order' => 0,
                'active' => true,
            ]],
            'magento' => [
                2 => ['id' => 2, 'parent_id' => 1, 'label' => 'Root', 'position' => 0, 'level' => 1, 'active' => true],
                10 => [
                    'id' => 10,
                    'parent_id' => 2,
                    'label' => 'Chairs',
                    'position' => 1,
                    'level' => 2,
                    'active' => true,
                ],
                11 => ['id' => 11, 'parent_id' => 2, 'label' => 'Old', 'position' => 2, 'level' => 2, 'active' => true],
            ],
            'database_mappings' => ['chairs' => 10, 'removed-category' => 11],
            'fresh' => ['complete' => true],
        ];
    }

    private function identityResolver(): CategoryIdentityResolver
    {
        return new CategoryIdentityResolver(
            new CategoryNameNormalizer(),
            new CategoryDeletionCandidateResolver()
        );
    }

    private function lock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);

        return new CategorySynchronizationLock($manager);
    }
    private function cacheInvalidator(): CategoryCacheInvalidator
    {
        $invalidator = $this->createStub(CategoryCacheInvalidator::class);
        $invalidator->method('defer')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        return $invalidator;
    }
}
