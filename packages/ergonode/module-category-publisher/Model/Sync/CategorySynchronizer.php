<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizerInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisher\Model\Data\CategorySynchronizationResult;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Magento\Framework\Exception\LocalizedException;

class CategorySynchronizer implements CategorySynchronizerInterface, CategoryBatchSynchronizerInterface
{
    private const int MAX_STAGES = 20;

    public function __construct(
        private readonly CategoryStateLoader $loader,
        private readonly CategorySyncPlanner $syncPlanner,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly StrictCategoryCreator $strictCreator
    ) {
    }

    public function synchronize(
        CategoryStateInterface $desiredState,
        string $mode = self::MODE_UPDATE
    ): CategorySynchronizationResultInterface {
        return $this->synchronizeBatch([$desiredState], $mode)[$desiredState->getCode()];
    }

    public function synchronizeBatch(array $desiredStates, string $mode = self::MODE_UPDATE): array
    {
        $this->validateMode($mode);
        $states = $this->indexStates($desiredStates);
        if ($mode === self::MODE_CREATE_STRICT) {
            return $this->strictCreator->create($states);
        }
        $mutationResults = array_fill_keys(array_keys($states), []);
        $terminal = [];

        for ($step = 0; $step < self::MAX_STAGES; ++$step) {
            if (count($terminal) === count($states)) {
                return $this->orderedResults($states, $terminal);
            }
            $operations = [];
            $operationOwners = [];
            $activeStates = [];
            $globalOperationKeys = [];
            $languagesByCode = [];
            foreach (array_diff_key($states, $terminal) as $code => $desiredState) {
                $languagesByCode[$code] = $this->languages($desiredState, $mode);
            }
            $remoteStates = $this->loader->loadBatch($languagesByCode);
            $stagePlanner = $this->syncPlanner->forBatch(array_diff_key($states, $terminal), $remoteStates, $mode);
            foreach ($states as $code => $desiredState) {
                if (isset($terminal[$code])) {
                    continue;
                }
                try {
                    $remote = $remoteStates[$code];
                    $plan = $stagePlanner->plan($desiredState, $remote, $mode);
                } catch (GraphQlRequestException $exception) {
                    throw $exception;
                } catch (LocalizedException $exception) {
                    $terminal[$code] = $this->failedResult(
                        $mutationResults[$code],
                        $exception->getMessage()
                    );
                    continue;
                }
                if ($plan->isNoop()) {
                    $terminal[$code] = new CategorySynchronizationResult(
                        $mutationResults[$code] === []
                            ? CategorySynchronizationResultInterface::STATUS_NOOP
                            : CategorySynchronizationResultInterface::STATUS_SUCCESS,
                        $desiredState->isDeleted() && $remote === null
                            ? CategorySynchronizationResultInterface::REFERENCE_ABSENT
                            : CategorySynchronizationResultInterface::REFERENCE_PRESENT,
                        $mutationResults[$code]
                    );
                    continue;
                }
                $stage = $plan->getStages()[0] ?? [];
                if ($stage === []) {
                    $terminal[$code] = $this->failedResult(
                        $mutationResults[$code],
                        (string)__('Category synchronization cannot progress.')
                    );
                    continue;
                }
                foreach ($stage as $operation) {
                    $globalOperationKey = $operation->getMetadata()['global_operation_key'] ?? null;
                    if (is_string($globalOperationKey) && isset($globalOperationKeys[$globalOperationKey])) {
                        continue;
                    }
                    if (is_string($globalOperationKey)) {
                        $globalOperationKeys[$globalOperationKey] = true;
                    }
                    $operations[] = $operation;
                    $operationOwners[spl_object_id($operation)] = $code;
                }
                $activeStates[$code] = $desiredState;
            }
            if (count($terminal) === count($states)) {
                return $this->orderedResults($states, $terminal);
            }
            if ($operations === []) {
                throw new LocalizedException(__('Category synchronization cannot progress.'));
            }
            foreach ($this->batchPlanner->plan($operations) as $plannedBatch) {
                $dispatchOperations = $this->nonTerminalOperations($plannedBatch, $operationOwners, $terminal);
                if ($dispatchOperations === []) {
                    continue;
                }
                $dispatchBatches = count($dispatchOperations) === count($plannedBatch->getOperationsByAlias())
                    ? [$plannedBatch]
                    : $this->batchPlanner->plan($dispatchOperations);
                foreach ($dispatchBatches as $batch) {
                    $batchStates = [];
                    foreach ($batch->getOperationsByAlias() as $operation) {
                        $owner = $operationOwners[spl_object_id($operation)];
                        $batchStates[$owner] = $activeStates[$owner];
                    }
                    $verifier = new CategoryBatchMutationVerifier(
                        $this->loader,
                        $this->syncPlanner,
                        $batchStates,
                        $operationOwners,
                        $mode
                    );
                    $stageResult = $this->executor->execute($batch, $verifier);
                    $this->collectResults(
                        $stageResult->getResults(),
                        $operationOwners,
                        $mutationResults,
                        $terminal
                    );
                }
            }
        }

        foreach (array_keys($states) as $code) {
            if (!isset($terminal[$code])) {
                $terminal[$code] = $this->failedResult(
                    $mutationResults[$code],
                    (string)__('Category synchronization did not converge after %1 stages.', self::MAX_STAGES)
                );
            }
        }

        return $this->orderedResults($states, $terminal);
    }

    private function validateMode(string $mode): void
    {
        if (!in_array($mode, [
            self::MODE_CREATE_ONLY,
            self::MODE_CREATE_STRICT,
            self::MODE_UPDATE,
            self::MODE_RECONCILE,
        ], true)) {
            throw new LocalizedException(__('Unsupported category synchronization mode "%1".', $mode));
        }
    }

    /**
     * @param CategoryStateInterface[] $desiredStates
     * @return array<string, CategoryStateInterface>
     */
    private function indexStates(array $desiredStates): array
    {
        $states = [];
        foreach ($desiredStates as $state) {
            if (!$state instanceof CategoryStateInterface) {
                throw new LocalizedException(__(
                    'Every category synchronization item must implement CategoryStateInterface.'
                ));
            }
            $code = $state->getCode();
            if (isset($states[$code])) {
                throw new LocalizedException(__('Category code "%1" occurs more than once in the batch.', $code));
            }
            $states[$code] = $state;
        }
        if ($states === []) {
            throw new LocalizedException(__('Category synchronization batch cannot be empty.'));
        }

        return $states;
    }

    /** @return string[] */
    private function languages(CategoryStateInterface $state, string $mode): array
    {
        return $mode === self::MODE_RECONCILE ? [] : array_keys($state->getNames());
    }

    /**
     * @param array<int, string> $operationOwners
     * @param array<string, CategorySynchronizationResultInterface> $terminal
     * @return MutationOperationInterface[]
     */
    private function nonTerminalOperations(
        MutationBatchInterface $batch,
        array $operationOwners,
        array $terminal
    ): array {
        return array_values(array_filter(
            $batch->getOperationsByAlias(),
            static fn (MutationOperationInterface $operation): bool => !isset(
                $terminal[$operationOwners[spl_object_id($operation)] ?? '']
            )
        ));
    }

    /**
     * @param MutationResultInterface[] $results
     * @param array<int, string> $operationOwners
     * @param array<string, MutationResultInterface[]> $mutationResults
     * @param array<string, CategorySynchronizationResultInterface> $terminal
     */
    private function collectResults(
        array $results,
        array $operationOwners,
        array &$mutationResults,
        array &$terminal
    ): void {
        foreach ($results as $result) {
            $code = $operationOwners[spl_object_id($result->getOperation())] ?? '';
            if (!isset($mutationResults[$code])) {
                throw new LocalizedException(__('Category mutation result has no correlated category code.'));
            }
            $mutationResults[$code][] = $result;
            if (!in_array($result->getStatus(), [
                MutationResultInterface::STATUS_SUCCESS,
                MutationResultInterface::STATUS_UNRESOLVED,
            ], true) && !isset($terminal[$code])) {
                $terminal[$code] = $this->failedResult(
                    $mutationResults[$code],
                    $this->failureMessage($result)
                );
            }
        }
    }

    /**
     * @param MutationResultInterface[] $results
     */
    private function failedResult(array $results, string $message): CategorySynchronizationResultInterface
    {
        return new CategorySynchronizationResult(
            CategorySynchronizationResultInterface::STATUS_FAILED,
            CategorySynchronizationResultInterface::REFERENCE_UNKNOWN,
            $results,
            $message
        );
    }

    private function failureMessage(MutationResultInterface $result): string
    {
        $errors = $result->getErrors();
        $message = is_array($errors[0] ?? null) ? trim((string)($errors[0]['message'] ?? '')) : '';

        return $message !== '' ? $message : 'Category mutation failed with status ' . $result->getStatus() . '.';
    }

    /**
     * @param array<string, CategoryStateInterface> $states
     * @param array<string, CategorySynchronizationResultInterface> $terminal
     * @return array<string, CategorySynchronizationResultInterface>
     */
    private function orderedResults(array $states, array $terminal): array
    {
        $ordered = [];
        foreach (array_keys($states) as $code) {
            $ordered[$code] = $terminal[$code];
        }

        return $ordered;
    }
}
