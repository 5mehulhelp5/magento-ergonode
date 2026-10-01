<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\CategoryPublisher\Model\Sync\CategoryBatchMutationVerifier;
use Ergonode\CategoryPublisher\Model\Sync\CategoryStateLoader;
use Ergonode\CategoryPublisher\Model\Sync\CategorySyncPlanner;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use PHPUnit\Framework\TestCase;

class CategoryBatchMutationVerifierTest extends TestCase
{
    public function testContributorBatchIsSharedWithinAttemptAndRebuiltAfterRetry(): void
    {
        $desired = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $states = ['chairs' => $desired];
        $factory = new CategoryMutationFactory();
        $operation = $factory->setName($desired);
        $mode = CategorySynchronizerInterface::MODE_UPDATE;
        $loader = $this->createMock(CategoryStateLoader::class);
        $loader->expects(self::exactly(2))->method('loadBatch')->willReturn($states);
        $pending = $this->createMock(CategorySynchronizationContributorInterface::class);
        $pending->expects(self::once())->method('planNext')->with($desired, $mode)->willReturn([$operation]);
        $applied = $this->createMock(CategorySynchronizationContributorInterface::class);
        $applied->expects(self::once())->method('planNext')->with($desired, $mode)->willReturn([]);
        $contributor = $this->createMock(CategoryBatchSynchronizationContributorInterface::class);
        $contributor->expects(self::never())->method('planNext');
        $contributor->expects(self::exactly(2))->method('forBatch')->with($states, $mode)
            ->willReturnOnConsecutiveCalls($pending, $applied);
        $verifier = new CategoryBatchMutationVerifier(
            $loader,
            new CategorySyncPlanner($factory, [$contributor]),
            $states,
            [spl_object_id($operation) => 'chairs'],
            $mode
        );
        foreach ([MutationVerificationResultInterface::STATUS_NOT_APPLIED,
            MutationVerificationResultInterface::STATUS_APPLIED] as $expected) {
            $verifier->beginVerificationRound();
            self::assertSame($expected, $verifier->verify($operation)->getStatus());
            self::assertSame($expected, $verifier->verify($operation)->getStatus());
        }
    }

    public function testVerificationSharesBatchReadAndRefreshesItForNextAttempt(): void
    {
        $chairs = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $tables = new CategoryStateDto('tables', ['pl_PL' => 'Stoły']);
        $factory = new CategoryMutationFactory();
        $first = $factory->setName($chairs);
        $second = $factory->setName($tables);
        $loader = $this->createMock(CategoryStateLoader::class);
        $loader->expects(self::exactly(2))->method('loadBatch')
            ->with(['chairs' => [], 'tables' => []])
            ->willReturnOnConsecutiveCalls(
                ['chairs' => new CategoryStateDto('chairs'), 'tables' => new CategoryStateDto('tables')],
                ['chairs' => $chairs, 'tables' => $tables]
            );
        $verifier = new CategoryBatchMutationVerifier(
            $loader,
            new CategorySyncPlanner($factory),
            ['chairs' => $chairs, 'tables' => $tables],
            [spl_object_id($first) => 'chairs', spl_object_id($second) => 'tables'],
            CategorySynchronizerInterface::MODE_RECONCILE
        );
        $verifier->beginVerificationRound();
        foreach ([$first, $second, $first] as $operation) {
            self::assertSame(
                MutationVerificationResultInterface::STATUS_NOT_APPLIED,
                $verifier->verify($operation)->getStatus()
            );
        }
        $verifier->beginVerificationRound();
        foreach ([$first, $second] as $operation) {
            self::assertSame(
                MutationVerificationResultInterface::STATUS_APPLIED,
                $verifier->verify($operation)->getStatus()
            );
        }
    }
}
