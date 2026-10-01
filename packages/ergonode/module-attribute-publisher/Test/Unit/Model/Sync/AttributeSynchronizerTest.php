<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Test\Unit\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\AttributePublisher\Model\Sync\AttributeStateLoader;
use Ergonode\AttributePublisher\Model\Sync\AttributeSynchronizer;
use Ergonode\AttributePublisher\Model\Sync\AttributeSyncPlanner;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use PHPUnit\Framework\TestCase;

class AttributeSynchronizerTest extends TestCase
{
    public function testCreateOnlyVerifiesUnitAfterCorrectingExistingDefinition(): void
    {
        $desired = new AttributeState('weight', 'unit', 'LOCAL', [], ['unitName' => 'KILOGRAM']);
        $before = new AttributeState('weight', 'unit', 'LOCAL', [], ['unitName' => 'GRAM']);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('weight', [])->willReturn($desired);
        $fields = [];
        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $this->recordingBatchPlanner($fields),
            $this->successfulExecutor()
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_CREATE_ONLY, $before);
        self::assertTrue($result->isSuccessful());
        self::assertSame(['attributeUnitSetUnit'], $fields);
        self::assertSame(AttributeSynchronizationResultInterface::REFERENCE_PRESENT, $result->getReferenceStatus());
    }

    public function testCreateSuccessWithoutReadableUnitDoesNotConfirmReadiness(): void
    {
        $desired = new AttributeState('weight', 'unit', 'LOCAL', [], ['unitName' => 'KILOGRAM']);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls(
            null,
            new AttributeState('weight', 'unit', 'LOCAL')
        );
        $fields = [];
        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $this->recordingBatchPlanner($fields),
            $this->successfulExecutor()
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_CREATE_ONLY);
        self::assertFalse($result->isSuccessful());
        self::assertSame(['attributeCreateUnit'], $fields);
        self::assertSame(AttributeSynchronizationResultInterface::STATUS_UNSUPPORTED, $result->getStatus());
        self::assertSame(AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN, $result->getReferenceStatus());
    }

    public function testInitialStateIsReusedButTheWriteIsVerifiedWithAFreshRead(): void
    {
        $desired = new AttributeState('material', 'text', 'GLOBAL', ['en_US' => 'New name']);
        $before = new AttributeState('material', 'text', 'GLOBAL', ['en_US' => 'Old name']);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('material', ['en_US'])->willReturn($desired);
        $fields = [];
        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $this->recordingBatchPlanner($fields),
            $this->successfulExecutor()
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_UPDATE, $before);
        self::assertTrue($result->isSuccessful());
        self::assertCount(1, $fields);
        self::assertCount(1, $result->getResults());
    }

    public function testReconcileLoadsUnfilteredLanguages(): void
    {
        $desired = new AttributeState('material', 'text', 'LOCAL', ['pl_PL' => 'Materiał'], ['unique' => false]);
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::once())->method('load')->with('material', [])->willReturn($desired);

        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $this->createStub(MutationBatchPlannerInterface::class),
            $this->createStub(MutationExecutorInterface::class)
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_RECONCILE);

        self::assertTrue($result->isSuccessful());
    }

    public function testCreateOnlyFinishesDependentMetadataForEntityCreatedInSameRun(): void
    {
        $desired = new AttributeState(
            'material',
            'text',
            'LOCAL',
            ['pl_PL' => 'Materiał'],
            ['unique' => false],
            ['source' => 'magento']
        );
        $afterCreate = new AttributeState(
            'material',
            'text',
            'LOCAL',
            ['pl_PL' => 'Materiał'],
            ['unique' => false]
        );
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(3))->method('load')->willReturnOnConsecutiveCalls(
            null,
            $afterCreate,
            $desired
        );
        $fields = [];
        $batchPlanner = $this->recordingBatchPlanner($fields);
        $executor = $this->successfulExecutor();

        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $batchPlanner,
            $executor
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_CREATE_ONLY);

        self::assertTrue($result->isSuccessful());
        self::assertSame(['attributeCreateText', 'attributeAddMetadata'], $fields);
        self::assertSame(AttributeSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        self::assertSame(AttributeSynchronizationResultInterface::REFERENCE_PRESENT, $result->getReferenceStatus());
    }

    public function testCreateOnlyResumesAnInterruptedCreateInANewCall(): void
    {
        $desired = new AttributeState(
            'material',
            'text',
            'LOCAL',
            ['pl_PL' => 'Materiał'],
            ['unique' => false],
            ['source' => 'magento']
        );
        $remote = new AttributeState(
            'material',
            'text',
            'LOCAL',
            ['pl_PL' => 'Materiał'],
            ['unique' => false]
        );
        $loader = $this->createMock(AttributeStateLoader::class);
        $loader->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls($remote, $desired);
        $fields = [];
        $batchPlanner = $this->recordingBatchPlanner($fields);
        $executor = $this->successfulExecutor();

        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $batchPlanner,
            $executor
        ))->synchronize($desired, AttributeSynchronizerInterface::MODE_CREATE_ONLY);

        self::assertSame(['attributeAddMetadata'], $fields);
        self::assertSame(AttributeSynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
    }

    public function testUnsupportedPlanIsReturnedAsTerminalOutcome(): void
    {
        $desired = new AttributeState('mystery', 'unsupported', 'LOCAL');
        $loader = $this->createStub(AttributeStateLoader::class);
        $loader->method('load')->willReturn(null);

        $result = (new AttributeSynchronizer(
            $loader,
            new AttributeSyncPlanner(new AttributeMutationFactory()),
            $this->createStub(MutationBatchPlannerInterface::class),
            $this->createStub(MutationExecutorInterface::class)
        ))->synchronize($desired);

        self::assertSame(AttributeSynchronizationResultInterface::STATUS_UNSUPPORTED, $result->getStatus());
        self::assertSame(AttributeSynchronizationResultInterface::REFERENCE_ABSENT, $result->getReferenceStatus());
        self::assertFalse($result->isSuccessful());
    }

    /** @param string[] $fields */
    private function recordingBatchPlanner(array &$fields): MutationBatchPlannerInterface
    {
        $batchPlanner = $this->createStub(MutationBatchPlannerInterface::class);
        $batchPlanner->method('plan')->willReturnCallback(
            static function (array $operations) use (&$fields): array {
                $fields[] = $operations[0]->getField();

                return [new MutationBatch('mutation Test { __typename }', [], ['operation' => $operations[0]])];
            }
        );

        return $batchPlanner;
    }

    private function successfulExecutor(): MutationExecutorInterface
    {
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            static function (MutationBatch $batch): SynchronizationResult {
                $operation = $batch->getOperationsByAlias()['operation'];

                return new SynchronizationResult([
                    new MutationResult(MutationResultInterface::STATUS_SUCCESS, 'operation', $operation),
                ]);
            }
        );

        return $executor;
    }
}
