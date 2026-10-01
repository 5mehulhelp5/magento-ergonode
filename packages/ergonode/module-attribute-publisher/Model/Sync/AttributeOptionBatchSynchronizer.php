<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeOptionBatchSynchronizerInterface;
use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionSynchronizationResult;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\AttributePublisher\Model\GraphQl\AttributeMutationFactory;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class AttributeOptionBatchSynchronizer implements AttributeOptionBatchSynchronizerInterface
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

    public function synchronizeBatch(string $attributeCode, array $desiredOptions): array
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Ergonode attribute code is required for option synchronization.'));
        }

        $options = $this->indexOptions($desiredOptions);
        $remoteAttribute = $this->stateLoader->load($attributeCode, $this->languages($options));
        if ($remoteAttribute === null) {
            throw new LocalizedException(__('Ergonode attribute "%1" was not found.', $attributeCode));
        }
        $type = $remoteAttribute->getType();
        if (!in_array($type, ['select', 'multi_select'], true)) {
            throw new LocalizedException(__('Ergonode attribute "%1" does not support options.', $attributeCode));
        }

        $remoteOptions = $this->indexRemoteOptions($remoteAttribute->getOptions());
        $operations = [];
        $operationOwners = [];
        $results = [];
        $rejected = [];

        foreach ($options as $code => $option) {
            $existingOption = $remoteOptions[$code] ?? null;
            if ($existingOption !== null
                && array_intersect_key($existingOption->getNames(), $option->getNames()) === $option->getNames()
            ) {
                $results[$code] = $this->existingResult();
                continue;
            }

            $operation = $existingOption === null
                ? $this->mutationFactory->addOption($attributeCode, $type, $option)
                : $this->mutationFactory->renameOption($attributeCode, $type, $option);
            $operations[] = $operation;
            $operationOwners[spl_object_id($operation)] = [
                'code' => $code,
                'existed' => $existingOption !== null,
            ];
        }

        if ($operations !== []) {
            foreach ($this->batchPlanner->plan($operations) as $batch) {
                foreach ($this->executor->execute($batch)->getResults() as $mutationResult) {
                    $owner = $operationOwners[spl_object_id($mutationResult->getOperation())] ?? null;
                    if (!is_array($owner) || !isset($options[$owner['code'] ?? ''])) {
                        throw new LocalizedException(__('Option mutation result has no correlated option code.'));
                    }
                    $code = (string)$owner['code'];
                    if ($mutationResult->getStatus() === MutationResultInterface::STATUS_VALIDATION_FAILURE) {
                        $rejected[$code] = $mutationResult;
                        continue;
                    }
                    $results[$code] = $this->result(
                        (bool)$owner['existed'],
                        $mutationResult
                    );
                }
            }
        }

        if ($rejected !== []) {
            $results += $this->verifyExistingOptions($attributeCode, $options, $rejected);
        }

        foreach (array_keys($options) as $code) {
            $results[$code] ??= new AttributeOptionSynchronizationResult(
                AttributeOptionSynchronizationResultInterface::STATUS_FAILED,
                AttributeOptionSynchronizationResultInterface::REFERENCE_UNKNOWN,
                [],
                (string)__('Ergonode returned no mutation result for option "%1".', $code)
            );
        }

        return $this->orderedResults($options, $results);
    }

    /**
     * @param AttributeOptionStateInterface[] $desiredOptions
     * @return array<string, AttributeOptionStateInterface>
     */
    private function indexOptions(array $desiredOptions): array
    {
        $options = [];
        foreach ($desiredOptions as $option) {
            if (!$option instanceof AttributeOptionStateInterface) {
                throw new LocalizedException(__(
                    'Every option synchronization item must implement AttributeOptionStateInterface.'
                ));
            }
            $code = $option->getCode();
            if (isset($options[$code])) {
                throw new LocalizedException(__('Option code "%1" occurs more than once in the batch.', $code));
            }
            $options[$code] = $option;
        }
        if ($options === []) {
            throw new LocalizedException(__('Option synchronization batch cannot be empty.'));
        }

        return $options;
    }

    /**
     * @param array<string, AttributeOptionStateInterface> $options
     * @return string[]
     */
    private function languages(array $options): array
    {
        $languages = [];
        foreach ($options as $option) {
            foreach (array_keys($option->getNames()) as $language) {
                $languages[$language] = true;
            }
        }

        return array_keys($languages);
    }

    /**
     * @param AttributeOptionStateInterface[] $options
     * @return array<string, AttributeOptionStateInterface>
     */
    private function indexRemoteOptions(array $options): array
    {
        $indexed = [];
        foreach ($options as $option) {
            $indexed[$option->getCode()] = $option;
        }

        return $indexed;
    }

    private function result(
        bool $existed,
        MutationResultInterface $mutationResult
    ): AttributeOptionSynchronizationResultInterface {
        if ($mutationResult->getStatus() === MutationResultInterface::STATUS_SUCCESS) {
            if ($existed) {
                return new AttributeOptionSynchronizationResult(
                    AttributeOptionSynchronizationResultInterface::STATUS_NOOP,
                    AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                    [$mutationResult],
                    (string)__('Option already exists in Ergonode and has been synchronized for mapping.')
                );
            }

            return new AttributeOptionSynchronizationResult(
                AttributeOptionSynchronizationResultInterface::STATUS_SUCCESS,
                AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                [$mutationResult]
            );
        }

        return $this->failedResult($mutationResult, $this->failureMessageFormatter->format($mutationResult, 'Option'));
    }

    /**
     * @param array<string, AttributeOptionStateInterface> $options
     * @param array<string, MutationResultInterface> $rejected
     * @return array<string, AttributeOptionSynchronizationResultInterface>
     */
    private function verifyExistingOptions(string $attributeCode, array $options, array $rejected): array
    {
        $results = [];
        $pending = [];
        try {
            $remote = $this->stateLoader->load($attributeCode, $this->languages($options));
            $remoteOptions = $remote === null ? [] : $this->indexRemoteOptions($remote->getOptions());
            foreach ($rejected as $code => $mutation) {
                $message = $this->failureMessageFormatter->format($mutation, 'Option');
                $existing = $remoteOptions[$code] ?? null;
                if ($existing === null) {
                    $results[$code] = $this->failedResult($mutation, $message);
                    continue;
                }
                $names = array_replace($existing->getNames(), $options[$code]->getNames());
                if ($names === $existing->getNames()) {
                    $results[$code] = new AttributeOptionSynchronizationResult(
                        AttributeOptionSynchronizationResultInterface::STATUS_NOOP,
                        AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                        [$mutation],
                        $message
                    );
                } else {
                    $pending[] = new AttributeOptionState($options[$code]->getCode(), $names);
                }
            }
            if ($pending !== [] && $remote !== null) {
                $desired = new AttributeState(
                    $remote->getCode(),
                    $remote->getType(),
                    $remote->getScope(),
                    $remote->getNames(),
                    $remote->getParameters(),
                    $remote->getMetadata(),
                    $pending
                );
                $verification = $this->attributeSynchronizer->synchronize(
                    $desired,
                    AttributeSynchronizerInterface::MODE_UPDATE,
                    $remote
                );
                foreach ($pending as $option) {
                    $code = $option->getCode();
                    $results[$code] = new AttributeOptionSynchronizationResult(
                        $verification->isSuccessful() ? AttributeOptionSynchronizationResultInterface::STATUS_NOOP
                            : AttributeOptionSynchronizationResultInterface::STATUS_FAILED,
                        AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
                        [$rejected[$code], ...$verification->getResults()],
                        $this->failureMessageFormatter->format($rejected[$code], 'Option')
                            . ' ' . $verification->getMessage()
                    );
                }
            }
        } catch (Throwable $exception) {
            foreach ($rejected as $code => $mutation) {
                $results[$code] ??= $this->failedResult(
                    $mutation,
                    $this->failureMessageFormatter->format($mutation, 'Option') . ' ' . $exception->getMessage()
                );
            }
        }
        return $results;
    }

    private function existingResult(): AttributeOptionSynchronizationResultInterface
    {
        return new AttributeOptionSynchronizationResult(
            AttributeOptionSynchronizationResultInterface::STATUS_NOOP,
            AttributeOptionSynchronizationResultInterface::REFERENCE_PRESENT,
            [],
            (string)__('Option already exists in Ergonode and is ready for mapping.')
        );
    }

    private function failedResult(
        MutationResultInterface $mutationResult,
        string $message
    ): AttributeOptionSynchronizationResultInterface {
        return new AttributeOptionSynchronizationResult(
            AttributeOptionSynchronizationResultInterface::STATUS_FAILED,
            AttributeOptionSynchronizationResultInterface::REFERENCE_UNKNOWN,
            [$mutationResult],
            $message
        );
    }

    /**
     * @param array<string, AttributeOptionStateInterface> $options
     * @param array<string, AttributeOptionSynchronizationResultInterface> $results
     * @return array<string, AttributeOptionSynchronizationResultInterface>
     */
    private function orderedResults(array $options, array $results): array
    {
        $ordered = [];
        foreach (array_keys($options) as $code) {
            $ordered[$code] = $results[$code];
        }

        return $ordered;
    }
}
