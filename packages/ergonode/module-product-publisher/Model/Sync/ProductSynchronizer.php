<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductCreationContextInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Api\Exception\MutationBatchCapacityException;
use Ergonode\Publisher\Model\Data\MutationBatch;
use Ergonode\Publisher\Model\Data\MutationResult;
use Magento\Framework\Exception\LocalizedException;

class ProductSynchronizer implements ProductSynchronizerInterface, ProductBatchSynchronizerInterface
{
    public function __construct(
        private readonly ProductIdentityRegistryInterface $identityRegistry,
        private readonly ProductPublicationMutationPlanner $mutationPlanner,
        private readonly ProductMutationFailureClassifier $failureClassifier,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly ProductMutationFailureReporter $failureReporter,
        private readonly NewProductMutationRecovery $newProductRecovery,
        private readonly NewProductVisibility $visibility
    ) {
    }

    public function synchronize(
        ProductStateInterface $desiredState,
        string $mode = self::MODE_UPDATE
    ): ProductSynchronizationResultInterface {
        return $this->synchronizeBatch([$desiredState], $mode)[0];
    }

    public function synchronizeBatch(array $desiredStates, string $mode = self::MODE_UPDATE): array
    {
        $this->validateMode($mode);
        $states = $this->indexStates($desiredStates);
        $results = array_fill_keys(array_keys($states), []);
        $terminal = [];
        $existing = $this->existingStateKeys($states, $terminal);
        $updateBase = $existing;
        $createdSkus = [];
        foreach ($states as $stateKey => $state) {
            if ($state instanceof ProductCreationContextInterface
                && $state->wasCreatedInCurrentSynchronization()
            ) {
                unset($updateBase[$stateKey]);
                $createdSkus[] = $state->getSku();
            }
        }
        $createConflicts = [];
        $groupedFallbacks = [];
        $missingProducts = [];

        $createOperations = [];
        foreach ($states as $stateKey => $state) {
            if (isset($terminal[$stateKey])) {
                continue;
            }
            if ($state->isDeleted()) {
                if ($mode === self::MODE_RECONCILE && isset($existing[$stateKey])) {
                    $createOperations[] = $this->mutationPlanner->delete($state);
                } else {
                    $terminal[$stateKey] = new ProductSynchronizationResult(
                        $state->getSku(),
                        ProductSynchronizationResultInterface::STATUS_NOOP
                    );
                }
                continue;
            }
            if (!isset($existing[$stateKey])) {
                $createOperations[] = $this->mutationPlanner->create($state);
            }
        }

        $acceptedFailure = function (
            MutationResultInterface $result,
            string $stateKey
        ) use (
            &$createConflicts,
            &$groupedFallbacks,
            &$missingProducts,
            $states
        ): bool {
            if ($this->failureClassifier->isCreateConflict($result)) {
                $createConflicts[$stateKey] = true;

                return true;
            }
            if ($this->failureClassifier->isIdempotentConflict($result)) {
                return true;
            }
            if ($this->failureClassifier->needsGroupedQuantityFallback($result)) {
                $groupedFallbacks[] = $this->mutationPlanner->groupedQuantityFallback($result);

                return true;
            }
            if ($this->failureClassifier->isNotFoundResult($result)
                && $states[$stateKey]->getIdentityMode() !== ProductIdentityInterface::MODE_MAPPED
            ) {
                $missingProducts[$stateKey] = true;

                return true;
            }

            return false;
        };
        $operations = $this->mutationPlanner->operations($states, $updateBase);
        // Validate the whole plan before CREATE, preserving the existing capacity failure behavior.
        if ($createOperations !== []) {
            $this->removeOversizedOperations([...$createOperations, ...$operations], $states, $results, $terminal);
        }
        $this->executeStage($createOperations, $states, $results, $terminal, $createdSkus, $acceptedFailure);
        $createdStates = array_filter(
            array_diff_key($states, $terminal),
            static fn (ProductStateInterface $state): bool => in_array($state->getSku(), $createdSkus, true)
        );
        $this->executeStage(
            $this->mutationPlanner->afterCreation($operations, $createdStates),
            $states,
            $results,
            $terminal,
            $createdSkus,
            $acceptedFailure
        );
        if ($createConflicts !== []) {
            $this->executeStage(
                $this->mutationPlanner->baseOperations($states, $createConflicts),
                $states,
                $results,
                $terminal,
                $createdSkus,
                fn (MutationResultInterface $result, string $_stateKey): bool =>
                    $this->failureClassifier->isIdempotentConflict($result)
            );
        }
        if ($groupedFallbacks !== []) {
            $this->executeStage($groupedFallbacks, $states, $results, $terminal, $createdSkus);
        }
        if ($missingProducts !== []) {
            $recoveryStates = array_filter(
                array_intersect_key($states, $missingProducts),
                static fn (ProductStateInterface $_state, string $stateKey): bool => !isset($terminal[$stateKey]),
                ARRAY_FILTER_USE_BOTH
            );
            $recoveryConflicts = [];
            $this->executeStage(
                array_map($this->mutationPlanner->create(...), array_values($recoveryStates)),
                $states,
                $results,
                $terminal,
                $createdSkus,
                function (MutationResultInterface $result, string $stateKey) use (&$recoveryConflicts): bool {
                    if (!$this->failureClassifier->isCreateConflict($result)) {
                        return false;
                    }
                    $recoveryConflicts[$stateKey] = true;

                    return true;
                }
            );
            $this->executeStage(
                $this->mutationPlanner->operations(
                    $recoveryStates,
                    $recoveryConflicts,
                    compareRemoteState: false
                ),
                $states,
                $results,
                $terminal,
                $createdSkus,
                fn (MutationResultInterface $result, string $_stateKey): bool =>
                    $this->failureClassifier->isIdempotentConflict($result)
            );
        }

        foreach ($states as $stateKey => $state) {
            if (!isset($terminal[$stateKey])) {
                $terminal[$stateKey] = new ProductSynchronizationResult(
                    $state->getSku(),
                    ProductSynchronizationResultInterface::STATUS_SUCCESS,
                    $results[$stateKey]
                );
            }
        }

        return $this->orderedResults($states, $terminal);
    }

    /**
     * @param MutationOperationInterface[] $operations
     * @param array<string, ProductStateInterface> $states
     * @param array<string, MutationResultInterface[]> $results
     * @param array<string, ProductSynchronizationResultInterface> $terminal
     * @param string[] $createdSkus
     * @param callable(MutationResultInterface, string): bool|null $acceptedFailure
     */
    private function executeStage(
        array $operations,
        array $states,
        array &$results,
        array &$terminal,
        array &$createdSkus,
        ?callable $acceptedFailure = null
    ): void {
        if ($operations === []) {
            return;
        }
        $operations = $this->removeOversizedOperations($operations, $states, $results, $terminal);
        if ($operations === []) {
            return;
        }
        foreach ($this->batchPlanner->plan($operations) as $plannedBatch) {
            $dispatchOperations = $this->nonTerminalOperations($plannedBatch, $terminal);
            if ($dispatchOperations === []) {
                continue;
            }
            foreach ($this->batchPlanner->plan($dispatchOperations) as $batch) {
                $batchResults = $this->executor->execute($batch)->getResults();
                foreach ($batchResults as $result) {
                    if ($result->getStatus() === MutationResultInterface::STATUS_SUCCESS
                        && ($result->getOperation()->getMetadata()['operation_key'] ?? '') === 'create'
                    ) {
                        $createdState = $states[$this->resultStateKey($result, $states)];
                        $createdSkus[] = $createdState->getSku();
                        if ($createdState->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED
                            && $createdState->getMagentoProductId() !== null
                        ) {
                            $this->identityRegistry->bindMappedBatch([
                                $createdState->getMagentoProductId() => $createdState->getSku(),
                            ]);
                        }
                    }
                }
                foreach ($this->newProductRecovery->recover($batchResults, $createdSkus) as $result) {
                    $stateKey = $this->resultStateKey($result, $states);
                    if ($result->getStatus() === MutationResultInterface::STATUS_SUCCESS) {
                        $results[$stateKey][] = $result;
                        continue;
                    }
                    if ($acceptedFailure !== null
                        && !(in_array($states[$stateKey]->getSku(), $createdSkus, true)
                            && $this->failureClassifier->isNotFoundResult($result)
                            && !$this->failureClassifier->isIdempotentConflict($result))
                        && $acceptedFailure($result, $stateKey)
                    ) {
                        $results[$stateKey][] = $this->acceptedResult($result);
                        continue;
                    }
                    $results[$stateKey][] = $result;
                    if (!isset($terminal[$stateKey])) {
                        $terminal[$stateKey] = new ProductSynchronizationResult(
                            $states[$stateKey]->getSku(),
                            $this->requiresAttention($result, $states[$stateKey])
                                ? ProductSynchronizationResultInterface::STATUS_ATTENTION
                                : ProductSynchronizationResultInterface::STATUS_FAILED,
                            $results[$stateKey],
                            $this->failureReporter->describe($result)
                        );
                    }
                }
            }
        }
    }

    /**
     * @param MutationOperationInterface[] $operations
     * @param array<string, ProductStateInterface> $states
     * @param array<string, MutationResultInterface[]> $results
     * @param array<string, ProductSynchronizationResultInterface> $terminal
     * @return MutationOperationInterface[]
     */
    private function removeOversizedOperations(
        array $operations,
        array $states,
        array &$results,
        array &$terminal
    ): array {
        $accepted = [];
        foreach ($operations as $operation) {
            try {
                $this->batchPlanner->plan([$operation]);
                $accepted[] = $operation;
            } catch (MutationBatchCapacityException $exception) {
                $stateKey = $this->stateKey((string)($operation->getMetadata()['entity_sku'] ?? ''));
                if (!isset($states[$stateKey])) {
                    throw $exception;
                }
                $result = new MutationResult(
                    MutationResultInterface::STATUS_PERMANENT_FAILURE,
                    $operation->getAlias() ?? $operation->getField(),
                    $operation,
                    errors: [['message' => $exception->getMessage()]]
                );
                $results[$stateKey][] = $result;
                $terminal[$stateKey] ??= new ProductSynchronizationResult(
                    $states[$stateKey]->getSku(),
                    ProductSynchronizationResultInterface::STATUS_FAILED,
                    $results[$stateKey],
                    $exception->getMessage()
                );
            }
        }

        return $accepted;
    }

    /**
     * @param array<string, ProductSynchronizationResultInterface> $terminal
     * @return MutationOperationInterface[]
     */
    private function nonTerminalOperations(MutationBatch $batch, array $terminal): array
    {
        return array_values(array_filter(
            $batch->getOperationsByAlias(),
            fn (MutationOperationInterface $operation): bool => !isset($terminal[$this->stateKey((string)(
                $operation->getMetadata()['entity_sku'] ?? ''
            ))])
        ));
    }

    /**
     * @param array<string, ProductStateInterface> $states
     * @param array<string, ProductSynchronizationResultInterface> $terminal
     * @return array<string, true>
     */
    private function existingStateKeys(array $states, array &$terminal): array
    {
        $productIds = [];
        foreach ($states as $state) {
            if ($state->getMagentoProductId() !== null) {
                $productIds[] = $state->getMagentoProductId();
            }
        }
        $identities = $this->identityRegistry->getIdentitiesByProductIds($productIds);
        $existing = [];
        $mapped = [];
        $mappedSkus = [];
        foreach ($states as $state) {
            if ($state->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED) {
                $mappedSkus[] = $state->getSku();
            }
        }
        $owners = [];
        $skuIdentities = $mappedSkus === [] ? [] : $this->identityRegistry->getIdentitiesByErgonodeSkus($mappedSkus);
        foreach ($skuIdentities as $identity) {
            $owners[$identity->getErgonodeSku()] = $identity->getProductId();
        }
        foreach ($states as $stateKey => $state) {
            $productId = $state->getMagentoProductId();
            if ($state->getIdentityMode() === ProductIdentityInterface::MODE_MAPPED) {
                $sku = trim($state->getSku());
                if ($productId === null || $sku === '' || strlen($sku) > 64
                    || $state->getErgonodeSku() !== $sku
                ) {
                    $terminal[$stateKey] = new ProductSynchronizationResult(
                        $state->getSku(),
                        ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING,
                        message: 'Mapped product requires a valid SKU from the configured Magento identity attribute.'
                    );
                    continue;
                }
                $identity = $identities[$productId] ?? null;
                if (isset($owners[$sku]) && $owners[$sku] !== $productId) {
                    $terminal[$stateKey] = new ProductSynchronizationResult(
                        $sku,
                        ProductSynchronizationResultInterface::STATUS_CONFLICT,
                        message: (string)__(
                            'Ergonode SKU "%1" is already bound to Magento product ID %2. '
                                . 'Reconcile the identity before publication.',
                            $sku,
                            $owners[$sku]
                        )
                    );
                    continue;
                }
                if ($identity !== null && ($identity->getIdentityMode() !== ProductIdentityInterface::MODE_MAPPED
                    || $identity->getErgonodeSku() !== $sku)) {
                    $terminal[$stateKey] = new ProductSynchronizationResult(
                        $sku,
                        ProductSynchronizationResultInterface::STATUS_CONFLICT,
                        message: (string)__(
                            'Magento product ID %1 is bound as "%2" to Ergonode SKU "%3". '
                                . 'Reconcile or migrate its identity before mapped publication.',
                            $productId,
                            $identity->getIdentityMode(),
                            $identity->getErgonodeSku()
                        )
                    );
                    continue;
                }
                $mapped[$stateKey] = $state;
            } elseif ($productId !== null && isset($identities[$productId])) {
                $existing[$stateKey] = true;
            }
        }

        if ($mapped !== []) {
            try {
                $visible = array_fill_keys($this->visibility->load(array_map(
                    static fn (ProductStateInterface $state): string => $state->getSku(),
                    array_values($mapped)
                )), true);
                foreach ($mapped as $stateKey => $state) {
                    if (isset($visible[$state->getSku()])) {
                        $existing[$stateKey] = true;
                    }
                }
            } catch (LocalizedException $exception) {
                foreach ($mapped as $stateKey => $state) {
                    $terminal[$stateKey] = new ProductSynchronizationResult(
                        $state->getSku(),
                        ProductSynchronizationResultInterface::STATUS_FAILED,
                        message: (string)__(
                            'Cannot verify Ergonode SKU "%1": %2',
                            $state->getSku(),
                            $exception->getMessage()
                        )
                    );
                }
            }
        }

        return $existing;
    }

    private function validateMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_CREATE_ONLY, self::MODE_UPDATE, self::MODE_RECONCILE], true)) {
            throw new LocalizedException(__('Unsupported product synchronization mode "%1".', $mode));
        }
    }

    /** @param ProductStateInterface[] $desiredStates @return array<string, ProductStateInterface> */
    private function indexStates(array $desiredStates): array
    {
        $states = [];
        foreach ($desiredStates as $state) {
            if (!$state instanceof ProductStateInterface) {
                throw new LocalizedException(__(
                    'Every product synchronization item must implement ProductStateInterface.'
                ));
            }
            $stateKey = $this->stateKey($state->getSku());
            if (isset($states[$stateKey])) {
                throw new LocalizedException(__(
                    'Product SKU "%1" occurs more than once in the batch.',
                    $state->getSku()
                ));
            }
            $states[$stateKey] = $state;
        }
        if ($states === []) {
            throw new LocalizedException(__('Product synchronization batch cannot be empty.'));
        }

        return $states;
    }

    /**
     * @param array<string, ProductStateInterface> $states
     * @param array<string, ProductSynchronizationResultInterface> $terminal
     * @return ProductSynchronizationResultInterface[]
     */
    private function orderedResults(array $states, array $terminal): array
    {
        return array_values(array_map(
            static fn (string $stateKey): ProductSynchronizationResultInterface => $terminal[$stateKey],
            array_keys($states)
        ));
    }

    /** @param array<string, ProductStateInterface> $states */
    private function resultStateKey(MutationResultInterface $result, array $states): string
    {
        $sku = (string)($result->getOperation()->getMetadata()['entity_sku'] ?? '');
        $stateKey = $this->stateKey($sku);
        if (!isset($states[$stateKey])) {
            throw new LocalizedException(__('Product mutation result is missing valid SKU correlation metadata.'));
        }

        return $stateKey;
    }

    private function stateKey(string $sku): string
    {
        return 'sku:' . $sku;
    }

    private function requiresAttention(
        MutationResultInterface $result,
        ProductStateInterface $state
    ): bool {
        return in_array($result->getStatus(), [
            MutationResultInterface::STATUS_TRANSIENT_FAILURE,
            MutationResultInterface::STATUS_UNRESOLVED,
        ], true) || (in_array($state->getIdentityMode(), [
            ProductIdentityInterface::MODE_ASSIGNED,
            ProductIdentityInterface::MODE_MAPPED,
        ], true)
            && $this->failureClassifier->isNotFoundResult($result));
    }

    private function acceptedResult(MutationResultInterface $result): MutationResultInterface
    {
        return new MutationResult(
            MutationResultInterface::STATUS_SUCCESS,
            $result->getAlias(),
            $result->getOperation(),
            $result->getData(),
            attempts: $result->getAttempts()
        );
    }
}
