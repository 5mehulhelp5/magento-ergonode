<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;

class NewProductMutationRecovery
{
    /** @var int[] */
    private readonly array $delaysMilliseconds;

    /** @param array<int, int|float|string> $delaysMilliseconds */
    public function __construct(
        private readonly NewProductVisibility $visibility,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly ProductMutationFailureClassifier $failureClassifier,
        private readonly int $maxIterations = 5,
        array $delaysMilliseconds = [100, 200, 300, 500, 800]
    ) {
        if ($maxIterations < 0 || $delaysMilliseconds === []) {
            throw new InvalidArgumentException(
                'New product recovery requires a nonnegative iteration limit and delays.'
            );
        }
        foreach ($delaysMilliseconds as $delay) {
            if (!is_numeric($delay) || (float)$delay < 0 || (float)(int)$delay !== (float)$delay) {
                throw new InvalidArgumentException('New product recovery delays must be nonnegative milliseconds.');
            }
        }
        $this->delaysMilliseconds = array_values(array_map(intval(...), $delaysMilliseconds));
    }

    /**
     * Recover only rejected writes after a confirmed create; successful or ambiguous writes are never replayed.
     *
     * @param MutationResultInterface[] $results
     * @param string[] $createdSkus
     * @return MutationResultInterface[]
     */
    public function recover(array $results, array $createdSkus): array
    {
        $pending = [];
        foreach ($results as $index => $result) {
            if ($this->canRecover($result, $createdSkus)) {
                $pending[$index] = $result;
            }
        }
        for ($iteration = 0; $pending !== [] && $iteration <= $this->maxIterations; ++$iteration) {
            $skus = array_values(array_unique(array_map($this->sku(...), $pending)));
            $delay = $iteration === 0 ? 0 : $this->delaysMilliseconds[
                min($iteration - 1, count($this->delaysMilliseconds) - 1)
            ];
            try {
                $visible = $this->visibility->load($skus, $delay);
            } catch (LocalizedException $exception) {
                return $this->unresolved($results, $pending, (string)__(
                    'Could not verify the newly created product: %1. Its SKU mapping was retained.',
                    $exception->getMessage()
                ));
            }
            $ready = array_filter(
                $pending,
                fn (MutationResultInterface $result): bool => in_array($this->sku($result), $visible, true)
            );
            $pending = array_diff_key($pending, $ready);
            $results = $this->retryReady($results, $ready);
        }

        return $this->unresolved($results, $pending, (string)__(
            'The newly created product is still unavailable after the initial check and %1 retry iterations. '
                . 'Its SKU mapping was retained; remaining product data was not sent.',
            $this->maxIterations
        ));
    }

    /** @param string[] $createdSkus */
    private function canRecover(MutationResultInterface $result, array $createdSkus): bool
    {
        $key = (string)($result->getOperation()->getMetadata()['operation_key'] ?? '');

        return $result->getStatus() === MutationResultInterface::STATUS_VALIDATION_FAILURE
            && in_array($this->sku($result), $createdSkus, true)
            && !in_array($key, ['create', 'identity:create', 'delete'], true)
            && !$this->failureClassifier->isIdempotentConflict($result)
            && !$this->failureClassifier->needsGroupedQuantityFallback($result);
    }

    /**
     * @param MutationResultInterface[] $results
     * @param array<int, MutationResultInterface> $ready
     * @return MutationResultInterface[]
     */
    private function retryReady(array $results, array $ready): array
    {
        if ($ready === []) {
            return $results;
        }
        $operations = [];
        $owners = [];
        foreach ($ready as $index => $result) {
            $operation = $result->getOperation();
            $operations[] = $operation;
            $owners[spl_object_id($operation)] = $index;
        }
        foreach ($this->batchPlanner->plan($operations) as $batch) {
            foreach ($this->executor->execute($batch)->getResults() as $retried) {
                $index = $owners[spl_object_id($retried->getOperation())];
                $original = $results[$index];
                $results[$index] = new MutationResult(
                    $retried->getStatus(),
                    $original->getAlias(),
                    $original->getOperation(),
                    $retried->getData(),
                    $retried->getErrors(),
                    $original->getAttempts() + $retried->getAttempts()
                );
            }
        }

        return $results;
    }

    /**
     * @param MutationResultInterface[] $results
     * @param array<int, MutationResultInterface> $pending
     * @return MutationResultInterface[]
     */
    private function unresolved(array $results, array $pending, string $message): array
    {
        foreach ($pending as $index => $result) {
            $results[$index] = new MutationResult(
                MutationResultInterface::STATUS_UNRESOLVED,
                $result->getAlias(),
                $result->getOperation(),
                $result->getData(),
                [...$result->getErrors(), [
                    'message' => $message,
                    'extensions' => ['code' => 'PRODUCT_VISIBILITY_UNCONFIRMED'],
                ]],
                $result->getAttempts()
            );
        }

        return $results;
    }

    private function sku(MutationResultInterface $result): string
    {
        return (string)($result->getOperation()->getMetadata()['entity_sku'] ?? '');
    }
}
