<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Ergonode\Publisher\Api\MutationBatchBuilderInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Api\MutationVerificationRoundInterface;
use Ergonode\Publisher\Api\RetryDelayInterface;
use Ergonode\Publisher\Model\Data\MutationResult;
use Ergonode\Publisher\Model\Data\SynchronizationResult;

class MutationExecutor implements MutationExecutorInterface
{
    /**
     * @param int[] $transientHttpStatuses
     */
    public function __construct(
        private readonly GraphQlMutationClientInterface $client,
        private readonly MutationBatchBuilderInterface $batchBuilder,
        private readonly MutationErrorMapper $errorMapper,
        private readonly RetryDelayInterface $retryDelay,
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelaySeconds = 1,
        private readonly array $transientHttpStatuses = [500, 502, 503, 504]
    ) {
    }

    public function execute(
        MutationBatchInterface $batch,
        ?AmbiguousMutationVerifierInterface $verifier = null
    ): SynchronizationResultInterface {
        if ($batch->isEmpty()) {
            return new SynchronizationResult([]);
        }

        $attemptLimit = max(1, $this->maxAttempts);
        $attempts = [];
        $originalAliases = [];
        $results = [];

        foreach ($batch->getOperationsByAlias() as $alias => $operation) {
            $operationId = spl_object_id($operation);
            $attempts[$operationId] = 0;
            $originalAliases[$operationId] = $alias;
        }

        $currentBatch = $batch;
        while (!$currentBatch->isEmpty()) {
            if ($verifier instanceof MutationVerificationRoundInterface) {
                $verifier->beginVerificationRound();
            }
            foreach ($currentBatch->getOperationsByAlias() as $operation) {
                ++$attempts[spl_object_id($operation)];
            }

            try {
                $response = $this->client->mutateWithResponse(
                    $currentBatch->getDocument(),
                    $currentBatch->getVariables()
                );
            } catch (GraphQlRequestException $exception) {
                $retryOperations = $this->handleTransportFailure(
                    $currentBatch,
                    $exception,
                    $verifier,
                    $attempts,
                    $originalAliases,
                    $results,
                    $attemptLimit
                );
                if ($retryOperations === []) {
                    break;
                }

                $this->retryDelay->wait($this->resolveDelay($attempts, $retryOperations, $exception));
                $currentBatch = $this->batchBuilder->build($retryOperations);
                continue;
            }

            $retryOperations = $this->handleResponse(
                $currentBatch,
                $response,
                $verifier,
                $attempts,
                $originalAliases,
                $results,
                $attemptLimit
            );
            if ($retryOperations === []) {
                break;
            }

            $this->retryDelay->wait($this->resolveDelay($attempts, $retryOperations));
            $currentBatch = $this->batchBuilder->build($retryOperations);
        }

        $orderedResults = [];
        foreach ($batch->getOperationsByAlias() as $operation) {
            $orderedResults[] = $results[spl_object_id($operation)];
        }

        return new SynchronizationResult($orderedResults);
    }

    /**
     * @param array<string, mixed> $response
     * @param array<int, int> $attempts
     * @param array<int, string> $originalAliases
     * @param array<int, MutationResult> $results
     * @return MutationOperationInterface[]
     */
    private function handleResponse(
        MutationBatchInterface $batch,
        array $response,
        ?AmbiguousMutationVerifierInterface $verifier,
        array $attempts,
        array $originalAliases,
        array &$results,
        int $attemptLimit
    ): array {
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $errors = $this->normalizeErrors($response['errors'] ?? []);
        $retryOperations = [];
        $knownAliases = array_keys($batch->getOperationsByAlias());

        foreach ($batch->getOperationsByAlias() as $alias => $operation) {
            $operationId = spl_object_id($operation);
            $operationErrors = $this->errorMapper->forAlias($errors, $alias, $knownAliases);

            if ($operationErrors === [] && array_key_exists($alias, $data)) {
                $results[$operationId] = new MutationResult(
                    MutationResultInterface::STATUS_SUCCESS,
                    $originalAliases[$operationId],
                    $operation,
                    $data[$alias],
                    [],
                    $attempts[$operationId]
                );
                continue;
            }

            if ($operationErrors !== [] && !$this->errorMapper->isTransient($operationErrors)) {
                $results[$operationId] = new MutationResult(
                    MutationResultInterface::STATUS_VALIDATION_FAILURE,
                    $originalAliases[$operationId],
                    $operation,
                    $data[$alias] ?? null,
                    $operationErrors,
                    $attempts[$operationId]
                );
                continue;
            }

            $status = $operationErrors === []
                ? MutationResultInterface::STATUS_UNRESOLVED
                : MutationResultInterface::STATUS_TRANSIENT_FAILURE;
            $this->verifyOrResolve(
                $operation,
                $status,
                $operationErrors,
                $verifier,
                $attempts,
                $originalAliases,
                $results,
                $retryOperations,
                $attemptLimit
            );
        }

        return $retryOperations;
    }

    /**
     * @param array<int, int> $attempts
     * @param array<int, string> $originalAliases
     * @param array<int, MutationResult> $results
     * @return MutationOperationInterface[]
     */
    private function handleTransportFailure(
        MutationBatchInterface $batch,
        GraphQlRequestException $exception,
        ?AmbiguousMutationVerifierInterface $verifier,
        array $attempts,
        array $originalAliases,
        array &$results,
        int $attemptLimit
    ): array {
        $retryOperations = [];
        $isTransient = $this->isTransientException($exception);
        $isSafeToRetry = $this->isSafeToRetryWithoutVerification($exception);
        $errors = [[
            'message' => $exception->getMessage(),
            'extensions' => array_filter([
                'failure_type' => $exception->getFailureType(),
                'http_status' => $exception->getHttpStatus(),
                'retry_after_seconds' => $exception->getRetryAfterSeconds(),
            ], static fn (mixed $value): bool => $value !== null),
        ]];

        foreach ($batch->getOperationsByAlias() as $operation) {
            $operationId = spl_object_id($operation);
            if (!$isTransient) {
                $results[$operationId] = new MutationResult(
                    MutationResultInterface::STATUS_PERMANENT_FAILURE,
                    $originalAliases[$operationId],
                    $operation,
                    null,
                    $errors,
                    $attempts[$operationId]
                );
                continue;
            }

            if ($isSafeToRetry) {
                if ($attempts[$operationId] < $attemptLimit) {
                    $retryOperations[] = $operation;
                } else {
                    $results[$operationId] = new MutationResult(
                        MutationResultInterface::STATUS_TRANSIENT_FAILURE,
                        $originalAliases[$operationId],
                        $operation,
                        null,
                        $errors,
                        $attempts[$operationId]
                    );
                }
                continue;
            }

            $this->verifyOrResolve(
                $operation,
                MutationResultInterface::STATUS_TRANSIENT_FAILURE,
                $errors,
                $verifier,
                $attempts,
                $originalAliases,
                $results,
                $retryOperations,
                $attemptLimit
            );
        }

        return $retryOperations;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, int> $attempts
     * @param array<int, string> $originalAliases
     * @param array<int, MutationResult> $results
     * @param MutationOperationInterface[] $retryOperations
     */
    private function verifyOrResolve(
        MutationOperationInterface $operation,
        string $failureStatus,
        array $errors,
        ?AmbiguousMutationVerifierInterface $verifier,
        array $attempts,
        array $originalAliases,
        array &$results,
        array &$retryOperations,
        int $attemptLimit
    ): void {
        $operationId = spl_object_id($operation);
        $verification = null;

        if ($verifier !== null) {
            try {
                $verification = $verifier->verify($operation);
            } catch (MutationVerificationException $exception) {
                $errors[] = ['message' => 'Verification failed: ' . $exception->getMessage()];
            }
        }

        if ($verification?->getStatus() === MutationVerificationResultInterface::STATUS_APPLIED) {
            $results[$operationId] = new MutationResult(
                MutationResultInterface::STATUS_SUCCESS,
                $originalAliases[$operationId],
                $operation,
                $verification->getData(),
                [],
                $attempts[$operationId]
            );

            return;
        }

        if ($verification?->getStatus() === MutationVerificationResultInterface::STATUS_NOT_APPLIED
            && $attempts[$operationId] < $attemptLimit
        ) {
            $retryOperations[] = $operation;

            return;
        }

        $results[$operationId] = new MutationResult(
            $failureStatus,
            $originalAliases[$operationId],
            $operation,
            null,
            $errors,
            $attempts[$operationId]
        );
    }

    private function isTransientException(GraphQlRequestException $exception): bool
    {
        if (!$exception->isTransient()) {
            return false;
        }

        $status = $exception->getHttpStatus();
        if ($exception->isAmbiguous() && $status !== null) {
            return in_array($status, $this->transientHttpStatuses, true);
        }

        return true;
    }

    private function isSafeToRetryWithoutVerification(GraphQlRequestException $exception): bool
    {
        return $exception->isSafeToRetry();
    }

    /**
     * @param array<int, int> $attempts
     * @param MutationOperationInterface[] $operations
     */
    private function resolveDelay(
        array $attempts,
        array $operations,
        ?GraphQlRequestException $exception = null
    ): int {
        $highestAttempt = 1;
        foreach ($operations as $operation) {
            $highestAttempt = max($highestAttempt, $attempts[spl_object_id($operation)]);
        }

        $retryAfter = 0;
        if ($exception !== null) {
            $retryAfter = $exception->getRetryAfterSeconds() ?? 0;
        }

        return max($retryAfter, max(0, $this->baseDelaySeconds) * $highestAttempt);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeErrors(mixed $errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $normalized = [];
        foreach ($errors as $error) {
            if (is_array($error)) {
                /** @var array<string, mixed> $error */
                $normalized[] = $error;
            }
        }

        return $normalized;
    }
}
