<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryPublisher\Api\CategorySynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\CategoryPublisher\Model\Sync\CategorySyncPlanner;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use PHPUnit\Framework\TestCase;

class CategorySyncPlannerTest extends TestCase
{
    public function testBatchPreparationOnlyIncludesMatchingLiveCoreStates(): void
    {
        $states = [
            'matching' => new CategoryStateDto('matching'),
            'renamed' => new CategoryStateDto('renamed', ['pl_PL' => 'New']),
            'missing' => new CategoryStateDto('missing'),
            'deleted' => new CategoryStateDto('deleted', [], [], true),
        ];
        $remote = [
            'matching' => $states['matching'], 'renamed' => new CategoryStateDto('renamed'),
            'missing' => null, 'deleted' => new CategoryStateDto('deleted'),
        ];
        $prepared = $this->createMock(CategorySynchronizationContributorInterface::class);
        $prepared->expects(self::once())->method('planNext')->willReturn([]);
        $contributor = $this->createMock(CategoryBatchSynchronizationContributorInterface::class);
        $contributor->expects(self::once())->method('forBatch')
            ->with(['matching' => $states['matching']], CategorySynchronizerInterface::MODE_UPDATE)
            ->willReturn($prepared);
        $contributor->expects(self::never())->method('planNext');
        $planner = new CategorySyncPlanner(new CategoryMutationFactory(), [$contributor]);
        $batch = $planner->forBatch($states, $remote, CategorySynchronizerInterface::MODE_UPDATE);
        self::assertTrue($batch->plan($states['matching'], $remote['matching'], 'update')->isNoop());
        self::assertTrue($planner->forBatch($states, $remote, 'create_strict')
            ->plan($states['matching'], $remote['matching'], 'create_strict')->isNoop());
    }

    public function testCreatesThenProducesNoopForIdenticalCoreState(): void
    {
        $state = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $planner = new CategorySyncPlanner(new CategoryMutationFactory());

        self::assertSame('categoryCreate', $planner->plan(
            $state,
            null,
            CategorySynchronizerInterface::MODE_UPDATE
        )->getStages()[0][0]->getField());
        self::assertTrue($planner->plan($state, $state, CategorySynchronizerInterface::MODE_UPDATE)->isNoop());
    }

    public function testCoreNameStageRunsBeforeOptionalContributor(): void
    {
        $desired = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $remote = new CategoryStateDto('chairs', ['pl_PL' => 'Stare']);
        $contributor = $this->createMock(CategorySynchronizationContributorInterface::class);
        $contributor->expects(self::never())->method('planNext');

        $plan = (new CategorySyncPlanner(new CategoryMutationFactory(), [$contributor]))->plan(
            $desired,
            $remote,
            CategorySynchronizerInterface::MODE_UPDATE
        );

        self::assertSame('categorySetName', $plan->getStages()[0][0]->getField());
    }

    public function testDelegatesEnrichmentAfterCoreStateMatches(): void
    {
        $desired = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $remote = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $operation = $this->createStub(MutationOperationInterface::class);
        $contributor = $this->createMock(CategorySynchronizationContributorInterface::class);
        $contributor->expects(self::once())->method('planNext')->with(
            self::identicalTo($desired),
            CategorySynchronizerInterface::MODE_UPDATE
        )->willReturn([$operation]);

        $plan = (new CategorySyncPlanner(new CategoryMutationFactory(), [$contributor]))->plan(
            $desired,
            $remote,
            CategorySynchronizerInterface::MODE_UPDATE
        );

        self::assertSame([$operation], $plan->getStages()[0]);
    }

    public function testStrictCreateNeverUpdatesExistingCategoryOrCallsContributors(): void
    {
        $desired = new CategoryStateDto('promocja_10_99', ['pl_PL' => 'Promocja 10.99']);
        $remote = new CategoryStateDto('promocja_10_99', ['pl_PL' => 'Inna kategoria']);
        $contributor = $this->createMock(CategorySynchronizationContributorInterface::class);
        $contributor->expects(self::never())->method('planNext');

        $plan = (new CategorySyncPlanner(new CategoryMutationFactory(), [$contributor]))->plan(
            $desired,
            $remote,
            CategorySynchronizerInterface::MODE_CREATE_STRICT
        );

        self::assertTrue($plan->isNoop());
    }

    public function testDeleteIsOnlyPlannedInExplicitReconcileMode(): void
    {
        $desired = new CategoryStateDto('chairs', [], [], true);
        $remote = new CategoryStateDto('chairs');
        $planner = new CategorySyncPlanner(new CategoryMutationFactory());

        self::assertTrue($planner->plan($desired, $remote, CategorySynchronizerInterface::MODE_UPDATE)->isNoop());
        self::assertSame('categoryDelete', $planner->plan(
            $desired,
            $remote,
            CategorySynchronizerInterface::MODE_RECONCILE
        )->getStages()[0][0]->getField());
    }
}
