<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\Operation;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryRecorder;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Throwable;

class CategoryTreeHistoryCapture
{
    public function __construct(
        private readonly CategoryTreeStateProviderInterface $stateProvider,
        private readonly CategoryTreeHistoryRecorder $historyRecorder,
        private readonly OperationContext $operationContext,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
        private readonly HistoryConfig $config,
        private readonly CategorySynchronizationLock $synchronizationLock
    ) {
    }

    /**
     * Execute a category-tree mutation and persist its grouped net effect.
     *
     * Nested calls are deliberately not captured as separate operations. This makes a CLI or cron
     * synchronization, including its internal snapshot refreshes, one selectable history entry.
     *
     * @param list<int> $treeIds
     * @param callable(): mixed $operation
     * @param callable(mixed): array{status: string, summary: array<string, int|string|bool|null>} $describeResult
     * @throws Throwable
     */
    public function execute(
        string $operationCode,
        array $treeIds,
        callable $operation,
        callable $describeResult
    ): mixed {
        if ($this->operationContext->isActive() || !$this->config->isEnabled()) {
            return $operation();
        }

        return $this->synchronizationLock->execute(
            fn (): mixed => $this->capture($operationCode, $treeIds, $operation, $describeResult)
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param callable(mixed): array{status: string, summary: array<string, int|string|bool|null>} $describeResult
     */
    public function executeGrouped(string $code, callable $operation, callable $describeResult): mixed
    {
        if ($this->operationContext->isGrouped()
            || $this->operationContext->isActive()
            || !$this->config->isEnabled()
        ) {
            return $operation();
        }
        return $this->synchronizationLock->execute(function () use ($code, $operation, $describeResult): mixed {
            $id = $this->historyRecorder->begin($code, $this->dateTime->gmtDate());
            $this->operationContext->beginGroup($id);
            try {
                $result = $operation();
                $description = $describeResult($result);
                $this->historyRecorder->finish($id, $description['status'], $description['summary']);
                return $result;
            } catch (Throwable $exception) {
                $this->historyRecorder->finish($id, 'failed', ['failed' => 1]);
                throw $exception;
            } finally {
                $this->operationContext->endGroup();
            }
        });
    }

    /**
     * @param list<int> $treeIds
     * @param callable(): mixed $operation
     * @param callable(mixed): array{status: string, summary: array<string, int|string|bool|null>} $describeResult
     */
    private function capture(
        string $code,
        array $treeIds,
        callable $operation,
        callable $describeResult
    ): mixed {
        $treeIds = array_values(array_unique(array_map('intval', $treeIds)));
        $startedAt = $this->dateTime->gmtDate();
        $beforeStates = $this->captureStates($treeIds);
        $description = ['status' => 'failed', 'summary' => ['failed' => 1]];
        $this->operationContext->enter();
        try {
            $result = $operation();
            $description = $describeResult($result);
            return $result;
        } finally {
            try {
                $afterStates = $this->captureStates($treeIds);
                if ($this->operationContext->isGrouped()) {
                    $this->historyRecorder->append(
                        $this->operationContext->getOperationId(),
                        $beforeStates,
                        $afterStates
                    );
                } else {
                    $this->historyRecorder->record(
                        $code,
                        $description['status'],
                        $startedAt,
                        $description['summary'],
                        $beforeStates,
                        $afterStates
                    );
                }
            } finally {
                $this->operationContext->leave();
            }
        }
    }

    /**
     * @param list<int> $treeIds
     * @return array<int, array<string, mixed>>
     */
    private function captureStates(array $treeIds): array
    {
        $states = [];
        foreach ($treeIds as $treeId) {
            if ($treeId <= 0) {
                continue;
            }
            try {
                $states[$treeId] = $this->stateProvider->getState($treeId);
            } catch (Throwable $exception) {
                $this->logger->error('Unable to capture Ergonode category tree state.', [
                    'category_tree_id' => $treeId,
                    'exception' => $exception,
                ]);
            }
        }

        return $states;
    }
}
