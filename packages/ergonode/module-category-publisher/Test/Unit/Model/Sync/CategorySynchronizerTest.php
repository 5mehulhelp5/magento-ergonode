<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Test\Unit\Model\Sync;

use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\CategoryPublisher\Model\Sync\CategoryStateLoader;
use Ergonode\CategoryPublisher\Model\Sync\CategorySynchronizer;
use Ergonode\CategoryPublisher\Model\Sync\CategorySyncPlanner;
use Ergonode\CategoryPublisher\Model\Sync\StrictCategoryCreator;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Publisher\Api\RetryDelayInterface;
use Ergonode\Publisher\Model\GraphQl\MutationAliasGenerator;
use Ergonode\Publisher\Model\GraphQl\MutationBatchBuilder;
use Ergonode\Publisher\Model\GraphQl\MutationBatchPlanner;
use Ergonode\Publisher\Model\GraphQl\MutationErrorMapper;
use Ergonode\Publisher\Model\GraphQl\MutationExecutor;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategorySynchronizerTest extends TestCase
{
    public function testReconcileLoadsUnfilteredLanguages(): void
    {
        $desired = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $loader = $this->createMock(CategoryStateLoader::class);
        $loader->expects(self::once())->method('loadBatch')->with(['chairs' => []])->willReturn(['chairs' => $desired]);

        $result = (new CategorySynchronizer(
            $loader,
            new CategorySyncPlanner(new CategoryMutationFactory()),
            $this->createStub(MutationBatchPlannerInterface::class),
            $this->createStub(MutationExecutorInterface::class),
            $this->createStub(StrictCategoryCreator::class)
        ))->synchronize($desired, CategorySynchronizerInterface::MODE_RECONCILE);

        self::assertTrue($result->isSuccessful());
    }

    public function testStrictCreateDelegatesWithoutLoadingReconciliationState(): void
    {
        $desired = new CategoryStateDto('chairs', ['pl_PL' => 'Krzesła']);
        $loader = $this->createMock(CategoryStateLoader::class);
        $loader->expects(self::never())->method('loadExistenceBatch');
        $creator = $this->createMock(StrictCategoryCreator::class);
        $creator->expects(self::once())->method('create')->with(['chairs' => $desired])->willReturn([]);

        (new CategorySynchronizer(
            $loader,
            new CategorySyncPlanner(new CategoryMutationFactory()),
            $this->createStub(MutationBatchPlannerInterface::class),
            $this->createStub(MutationExecutorInterface::class),
            $creator
        ))->synchronizeBatch([$desired], CategorySynchronizerInterface::MODE_CREATE_STRICT);
    }

    public function testBatchKeepsSuccessfulCategoryAfterSiblingValidationFailure(): void
    {
        $invalid = new CategoryStateDto('invalid', ['pl_PL' => 'Niepoprawna']);
        $valid = new CategoryStateDto('valid', ['pl_PL' => 'Poprawna']);
        $loads = ['invalid' => 0, 'valid' => 0];
        $loader = $this->createStub(CategoryStateLoader::class);
        $loader->method('loadBatch')->willReturnCallback(
            static function (array $languages) use (&$loads, $valid): array {
                $remote = [];
                foreach ($languages as $code => $_) {
                    $loads[$code]++;
                    $remote[$code] = $code === 'valid' && $loads[$code] > 1 ? $valid : null;
                }
                return $remote;
            }
        );
        $batchPlanner = $this->createStub(MutationBatchPlannerInterface::class);
        $batchPlanner->method('plan')->willReturnCallback(static function (array $operations): array {
            if ($operations === []) {
                return [];
            }
            $aliases = [];
            foreach ($operations as $index => $operation) {
                $aliases['operation_' . $index] = $operation;
            }

            return [new MutationBatch('mutation Test { __typename }', [], $aliases)];
        });
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(static function (MutationBatch $batch): SynchronizationResult {
            $results = [];
            foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                $code = (string)$operation->getVariables()['input']->getValue()['code'];
                $results[] = new MutationResult(
                    $code === 'invalid'
                        ? MutationResultInterface::STATUS_VALIDATION_FAILURE
                        : MutationResultInterface::STATUS_SUCCESS,
                    $alias,
                    $operation,
                    null,
                    $code === 'invalid' ? [['message' => 'Code is invalid.']] : []
                );
            }

            return new SynchronizationResult($results);
        });

        $results = (new CategorySynchronizer(
            $loader,
            new CategorySyncPlanner(new CategoryMutationFactory()),
            $batchPlanner,
            $executor,
            $this->createStub(StrictCategoryCreator::class)
        ))->synchronizeBatch([$invalid, $valid], CategorySynchronizerInterface::MODE_CREATE_ONLY);

        self::assertSame(CategorySynchronizationResultInterface::STATUS_FAILED, $results['invalid']->getStatus());
        self::assertSame('Code is invalid.', $results['invalid']->getMessage());
        self::assertSame(CategorySynchronizationResultInterface::STATUS_SUCCESS, $results['valid']->getStatus());
        self::assertSame(['invalid' => 1, 'valid' => 2], $loads);
    }

    public function testBatchRetriesUnresolvedCategoriesAfterEarlierSiblingFailure(): void
    {
        $invalid = new CategoryStateDto('invalid', ['pl_PL' => 'Niepoprawna']);
        $later = new CategoryStateDto('later', ['pl_PL' => 'Późniejsza']);
        $loads = ['invalid' => 0, 'later' => 0];
        $loader = $this->createStub(CategoryStateLoader::class);
        $loader->method('loadBatch')->willReturnCallback(
            static function (array $languages) use (&$loads, $later): array {
                $remote = [];
                foreach ($languages as $code => $_) {
                    $loads[$code]++;
                    $remote[$code] = $code === 'later' && $loads[$code] > 2 ? $later : null;
                }
                return $remote;
            }
        );
        $batchPlanner = $this->createStub(MutationBatchPlannerInterface::class);
        $batchPlanner->method('plan')->willReturnCallback(static function (array $operations): array {
            $aliases = [];
            foreach ($operations as $index => $operation) {
                $aliases['operation_' . $index] = $operation;
            }

            return [new MutationBatch('mutation Test { __typename }', [], $aliases)];
        });
        $executions = 0;
        $executor = $this->createStub(MutationExecutorInterface::class);
        $executor->method('execute')->willReturnCallback(
            static function (MutationBatch $batch) use (&$executions): SynchronizationResult {
                $executions++;
                $results = [];
                foreach ($batch->getOperationsByAlias() as $alias => $operation) {
                    $code = (string)$operation->getVariables()['input']->getValue()['code'];
                    $status = match ($code) {
                        'invalid' => MutationResultInterface::STATUS_VALIDATION_FAILURE,
                        'later' => $executions === 1
                            ? MutationResultInterface::STATUS_UNRESOLVED
                            : MutationResultInterface::STATUS_SUCCESS,
                    };
                    $results[] = new MutationResult(
                        $status,
                        $alias,
                        $operation,
                        null,
                        $code === 'invalid' ? [['message' => 'Code is invalid.']] : []
                    );
                }

                return new SynchronizationResult($results);
            }
        );

        $results = (new CategorySynchronizer(
            $loader,
            new CategorySyncPlanner(new CategoryMutationFactory()),
            $batchPlanner,
            $executor,
            $this->createStub(StrictCategoryCreator::class)
        ))->synchronizeBatch([$invalid, $later], CategorySynchronizerInterface::MODE_CREATE_ONLY);

        self::assertSame(CategorySynchronizationResultInterface::STATUS_FAILED, $results['invalid']->getStatus());
        self::assertSame(CategorySynchronizationResultInterface::STATUS_SUCCESS, $results['later']->getStatus());
        self::assertSame(2, $executions);
        self::assertSame(['invalid' => 1, 'later' => 3], $loads);
    }
    public function testFiftyNameUpdatesUseTwoReadRequestsAndOneMutation(): void
    {
        $states = [];
        $remote = [];
        for ($i = 0; $i < 50; $i++) {
            $code = 'c_' . $i;
            $states[] = new CategoryStateDto($code, ['pl_PL' => $i === 0 ? '' : ' New ' . $i . ' ']);
            $remote[$code] = ['code' => $code, 'name' => [['language' => 'pl_PL', 'value' => 'Old ' . $i]]];
        }
        $read = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $read->expects(self::exactly(3))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables) use (&$remote): array {
                $data = [];
                for ($i = 0; $i < 50; $i++) {
                    $data['category_' . $i] = $remote[$variables['code_' . $i]];
                }
                return $data;
            }
        );
        $write = $this->createMock(GraphQlMutationClientInterface::class);
        $write->expects(self::once())->method('mutateWithResponse')->willReturnCallback(
            static function (string $document, array $variables) use (&$remote): array {
                self::assertSame(50, substr_count($document, ': categorySetName('));
                $data = [];
                foreach ($variables as $key => $input) {
                    $remote[$input['code']]['name'] = $input['name'];
                    $data[substr($key, 0, -6)] = ['category' => ['code' => $input['code']]];
                }
                return ['data' => $data];
            }
        );
        $builder = new MutationBatchBuilder(
            new MutationAliasGenerator(),
            new Json()
        );
        $executor = new MutationExecutor(
            $write,
            $builder,
            new MutationErrorMapper(),
            $this->createStub(RetryDelayInterface::class)
        );
        $synchronizer = new CategorySynchronizer(
            new CategoryStateLoader($read),
            new CategorySyncPlanner(new CategoryMutationFactory()),
            new MutationBatchPlanner($builder),
            $executor,
            $this->createStub(StrictCategoryCreator::class)
        );
        foreach ($synchronizer->synchronizeBatch($states) as $result) {
            self::assertSame(CategorySynchronizationResultInterface::STATUS_SUCCESS, $result->getStatus());
        }
        foreach ($synchronizer->synchronizeBatch($states) as $result) {
            self::assertSame(CategorySynchronizationResultInterface::STATUS_NOOP, $result->getStatus());
        }
    }
}
