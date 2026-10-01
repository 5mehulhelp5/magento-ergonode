<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Ergonode\CategoryPublisher\Model\Data\CategorySynchronizationResult;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;

class StrictCategoryCreator
{
    public function __construct(
        private readonly CategoryStateLoader $loader,
        private readonly CategoryMutationFactory $mutations,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor
    ) {
    }

    /**
     * @param array<string, CategoryStateInterface> $states
     * @return array<string, CategorySynchronizationResultInterface>
     */
    public function create(array $states): array
    {
        $operations = [];
        $owners = [];
        $results = [];
        $deleted = array_filter($states, static fn (CategoryStateInterface $state): bool => $state->isDeleted());
        $remote = $deleted !== [] ? $this->loader->loadExistenceBatch(array_keys($deleted)) : [];
        foreach ($states as $code => $state) {
            if ($state->isDeleted()) {
                $results[$code] = new CategorySynchronizationResult(
                    CategorySynchronizationResultInterface::STATUS_NOOP,
                    isset($remote[$code])
                        ? CategorySynchronizationResultInterface::REFERENCE_PRESENT
                        : CategorySynchronizationResultInterface::REFERENCE_ABSENT
                );
                continue;
            }
            $operation = $this->mutations->create($state);
            $operations[] = $operation;
            $owners[spl_object_id($operation)] = $state->getCode();
        }

        if ($operations !== []) {
            foreach ($this->batchPlanner->plan($operations) as $batch) {
                $codes = array_map(
                    static fn (MutationOperationInterface $operation): string => $owners[spl_object_id($operation)],
                    $batch->getOperationsByAlias()
                );
                $outcome = $this->executor->execute(
                    $batch,
                    new StrictCategoryMutationVerifier($this->loader, array_values($codes))
                );
                $results += $this->resolveResults($outcome->getResults(), $owners);
            }
        }

        return array_replace(array_fill_keys(array_keys($states), new CategorySynchronizationResult(
            CategorySynchronizationResultInterface::STATUS_FAILED,
            CategorySynchronizationResultInterface::REFERENCE_UNKNOWN,
            [],
            'Ergonode did not return a category creation result.'
        )), $results);
    }

    /**
     * @param MutationResultInterface[] $mutationResults
     * @param array<int, string> $owners
     * @return array<string, CategorySynchronizationResultInterface>
     */
    private function resolveResults(array $mutationResults, array $owners): array
    {
        $results = [];
        $unconfirmed = [];
        foreach ($mutationResults as $result) {
            $code = $owners[spl_object_id($result->getOperation())];
            if ($result->getStatus() === MutationResultInterface::STATUS_SUCCESS
                && $this->confirmedCode($result->getData()) === $code
            ) {
                $results[$code] = $this->present($result, CategorySynchronizationResultInterface::STATUS_SUCCESS);
            } elseif (in_array($result->getStatus(), [
                MutationResultInterface::STATUS_SUCCESS,
                MutationResultInterface::STATUS_VALIDATION_FAILURE,
            ], true)) {
                $unconfirmed[$code] = $result;
            } else {
                $results[$code] = $this->failed($result);
            }
        }
        $remote = $unconfirmed !== [] ? $this->loader->loadExistenceBatch(array_keys($unconfirmed)) : [];
        foreach ($unconfirmed as $code => $result) {
            $results[$code] = isset($remote[$code])
                ? $this->present($result, $result->getStatus() === MutationResultInterface::STATUS_VALIDATION_FAILURE
                    ? CategorySynchronizationResultInterface::STATUS_NOOP
                    : CategorySynchronizationResultInterface::STATUS_SUCCESS)
                : $this->failed($result);
        }

        return $results;
    }

    private function confirmedCode(mixed $data): ?string
    {
        if ($data instanceof CategoryStateInterface) {
            return $data->getCode();
        }

        return is_array($data) && is_string($data['category']['code'] ?? null)
            ? $data['category']['code'] : null;
    }

    private function present(MutationResultInterface $result, string $status): CategorySynchronizationResultInterface
    {
        return new CategorySynchronizationResult(
            $status,
            CategorySynchronizationResultInterface::REFERENCE_PRESENT,
            [$result]
        );
    }

    private function failed(MutationResultInterface $result): CategorySynchronizationResultInterface
    {
        $errors = $result->getErrors();

        return new CategorySynchronizationResult(
            CategorySynchronizationResultInterface::STATUS_FAILED,
            CategorySynchronizationResultInterface::REFERENCE_UNKNOWN,
            [$result],
            (string)($errors[0]['message'] ?? 'Ergonode did not confirm category creation.')
        );
    }
}
