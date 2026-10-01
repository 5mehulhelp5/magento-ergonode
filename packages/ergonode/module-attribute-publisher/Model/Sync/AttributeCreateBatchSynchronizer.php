<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeCreateBatchSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeSynchronizationResult;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AttributeCreateBatchSynchronizer implements AttributeCreateBatchSynchronizerInterface
{
    public function __construct(
        private readonly AttributeMutationFactory $mutationFactory,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly AttributeStateLoader $stateLoader,
        private readonly AttributeSynchronizerInterface $attributeSynchronizer,
        private readonly MutationFailureMessageFormatter $failureMessageFormatter
    ) {
    }

    public function synchronizeBatch(array $desiredStates): array
    {
        $states = $this->indexStates($desiredStates);
        $operations = [];
        $operationOwners = [];

        foreach ($states as $code => $state) {
            if (!$this->mutationFactory->supports($state->getType())) {
                throw new LocalizedException(__(
                    'Ergonode attribute type "%1" cannot be created.',
                    $state->getType()
                ));
            }
            $operation = $this->mutationFactory->create($state);
            $operations[] = $operation;
            $operationOwners[spl_object_id($operation)] = $code;
        }

        $results = [];
        foreach ($this->batchPlanner->plan($operations) as $batch) {
            foreach ($this->executor->execute($batch)->getResults() as $mutationResult) {
                $code = $operationOwners[spl_object_id($mutationResult->getOperation())] ?? '';
                if (!isset($states[$code])) {
                    throw new LocalizedException(__(
                        'Attribute mutation result has no correlated attribute code.'
                    ));
                }
                $results[$code] = $this->result($states[$code], $mutationResult);
            }
        }

        foreach (array_keys($states) as $code) {
            $results[$code] ??= new AttributeSynchronizationResult(
                AttributeSynchronizationResultInterface::STATUS_FAILED,
                AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
                [],
                (string)__('Ergonode returned no mutation result for attribute "%1".', $code)
            );
        }

        return $this->orderedResults($states, $results);
    }

    /**
     * @param AttributeStateInterface[] $desiredStates
     * @return array<string, AttributeStateInterface>
     */
    private function indexStates(array $desiredStates): array
    {
        $states = [];
        foreach ($desiredStates as $state) {
            if (!$state instanceof AttributeStateInterface) {
                throw new LocalizedException(__(
                    'Every attribute synchronization item must implement AttributeStateInterface.'
                ));
            }
            $code = $state->getCode();
            if (isset($states[$code])) {
                throw new LocalizedException(__(
                    'Attribute code "%1" occurs more than once in the batch.',
                    $code
                ));
            }
            $states[$code] = $state;
        }
        if ($states === []) {
            throw new LocalizedException(__('Attribute synchronization batch cannot be empty.'));
        }

        return $states;
    }

    private function result(
        AttributeStateInterface $state,
        MutationResultInterface $mutationResult
    ): AttributeSynchronizationResultInterface {
        if ($mutationResult->getStatus() === MutationResultInterface::STATUS_SUCCESS) {
            return new AttributeSynchronizationResult(
                AttributeSynchronizationResultInterface::STATUS_SUCCESS,
                AttributeSynchronizationResultInterface::REFERENCE_PRESENT,
                [$mutationResult]
            );
        }

        if ($mutationResult->getStatus() === MutationResultInterface::STATUS_VALIDATION_FAILURE) {
            return $this->verifyExistingAttribute($state, $mutationResult);
        }

        return new AttributeSynchronizationResult(
            AttributeSynchronizationResultInterface::STATUS_FAILED,
            AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
            [$mutationResult],
            $this->failureMessageFormatter->format($mutationResult, 'Attribute')
        );
    }

    private function verifyExistingAttribute(
        AttributeStateInterface $state,
        MutationResultInterface $createResult
    ): AttributeSynchronizationResultInterface {
        $createMessage = $this->failureMessageFormatter->format($createResult, 'Attribute');

        try {
            $remote = $this->stateLoader->load($state->getCode(), array_keys($state->getNames()));
            if ($remote === null) {
                return $this->failedResult($createResult, $createMessage);
            }
            $verification = $this->attributeSynchronizer->synchronize(
                $state,
                AttributeSynchronizerInterface::MODE_CREATE_ONLY,
                $remote
            );
        } catch (Throwable $exception) {
            return $this->failedResult(
                $createResult,
                $createMessage . ' ' . (string)__(
                    'Existing Ergonode attribute verification failed: %1',
                    $exception->getMessage()
                )
            );
        }

        if ($verification->isSuccessful()) {
            return new AttributeSynchronizationResult(
                AttributeSynchronizationResultInterface::STATUS_NOOP,
                AttributeSynchronizationResultInterface::REFERENCE_PRESENT,
                [$createResult, ...$verification->getResults()],
                (string)__(
                    'Attribute already exists in Ergonode and has been verified for mapping. Ergonode response: %1',
                    $createMessage
                )
            );
        }

        return new AttributeSynchronizationResult(
            $verification->getStatus(),
            $verification->getReferenceStatus(),
            [$createResult, ...$verification->getResults()],
            $createMessage . ' ' . ($verification->getMessage() ?: (string)__(
                'The existing Ergonode attribute is not compatible with the Magento attribute.'
            ))
        );
    }

    private function failedResult(
        MutationResultInterface $mutationResult,
        string $message
    ): AttributeSynchronizationResultInterface {
        return new AttributeSynchronizationResult(
            AttributeSynchronizationResultInterface::STATUS_FAILED,
            AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
            [$mutationResult],
            $message
        );
    }

    /**
     * @param array<string, AttributeStateInterface> $states
     * @param array<string, AttributeSynchronizationResultInterface> $results
     * @return array<string, AttributeSynchronizationResultInterface>
     */
    private function orderedResults(array $states, array $results): array
    {
        $ordered = [];
        foreach (array_keys($states) as $code) {
            $ordered[$code] = $results[$code];
        }

        return $ordered;
    }
}
