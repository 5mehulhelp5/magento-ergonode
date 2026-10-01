<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationActionInterface;
use Ergonode\CategoryConsumer\Api\CategorySynchronizationRunInterface;
use Ergonode\CategoryConsumer\Api\CategorySynchronizationStateInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class CategorySynchronizationRun implements CategorySynchronizationRunInterface
{
    public function __construct(
        private readonly CategorySynchronizationStateInterface $storage,
        private readonly CategorySynchronizationProgress $progress,
        private readonly CategorySynchronizationLock $lock,
        private readonly CategorySynchronizationActionInterface $action,
        private readonly LoggerInterface $logger,
        private readonly CategoryReconciliationErrorFormatter $errorFormatter
    ) {
    }

    public function execute(string $runId, string $scope, string $action, bool $resume = false): array
    {
        if (!in_array($scope, ['tree', 'data', 'all'], true)
            || !in_array($action, ['sync', 'reset-cursor-and-sync'], true)
        ) {
            throw new LocalizedException(__('Choose an update action.'));
        }

        return $this->lock->execute(fn (): array => $this->run($runId, $scope, $action, $resume));
    }

    public function getStatus(string $runId): array
    {
        $state = $this->storage->get($runId) ?? ['state' => 'waiting'];
        if ($state['state'] === 'running' && !$this->lock->isRunning()) {
            $state['state'] = 'error';
            $state['message'] = (string)__(
                'Synchronization was interrupted. Check the saved changes before restarting.'
            );
        }
        $state['pause_requested'] = $this->storage->isPauseRequested($runId);

        return $state;
    }

    public function pause(string $runId): void
    {
        $this->storage->requestPause($runId);
    }

    /** @return array<string, mixed> */
    private function run(string $runId, string $scope, string $action, bool $resume): array
    {
        $state = $this->storage->get($runId);
        if ($resume && $state === null) {
            throw new LocalizedException(__('Synchronization progress expired. Start a new synchronization.'));
        }
        if ($state !== null) {
            if ($state['scope'] !== $scope || $state['action'] !== $action) {
                throw new LocalizedException(__('The synchronization scope cannot change when resuming.'));
            }
            if (in_array($state['state'], ['success', 'warning', 'error'], true)) {
                return $state;
            }
            if ($state['state'] !== 'paused') {
                throw new LocalizedException(__('This synchronization cannot be started again.'));
            }
            if (!$resume) {
                return $state;
            }
            $this->storage->clearPause($runId);
        }
        $state ??= [
            'scope' => $scope, 'action' => $action, 'results' => [],
            'started_at' => time(), 'stage' => 'starting', 'processed' => 0, 'total' => null,
        ];
        $state['state'] = 'running';
        $state['message'] = '';
        $this->storage->save($runId, $state);
        $this->progress->attach($runId, $state);
        try {
            $this->progress->checkpoint('validating');
            $result = $this->action->execute($scope, $action);
            $state = $this->storage->get($runId) ?? $state;
            $state['stats'] = $result;
            $state['state'] = (int)$result['conflicts'] > 0 ? 'warning' : 'success';
            $processed = (int)$result['events'] > 0 || (int)($result['results']['tree']['trees'] ?? 0) > 0;
            $state['message'] = (string)((int)$result['conflicts'] > 0
                ? __('Completed with %1 conflict(s).', $result['conflicts'])
                : ($processed ? __('Changes applied.') : __('No new changes in Ergonode.')));
        } catch (CategorySynchronizationPaused $exception) {
            $state = $this->storage->get($runId) ?? $state;
            $state['state'] = 'paused';
            $state['message'] = $exception->getMessage();
        } catch (Throwable $exception) {
            $state = $this->storage->get($runId) ?? $state;
            $state['state'] = 'error';
            $state['message'] = $exception instanceof LocalizedException
                ? $exception->getMessage() : (string)__('Could not update categories.');
            if ($exception instanceof GraphQlRequestException) {
                $state = array_replace($state, $this->errorFormatter->format($exception));
            }
            $state['message'] .= ' ' . (string)__(
                'Completed changes remain saved. The cursor was not advanced; retry is safe.'
            );
            $this->logger->error('Category synchronization failed.', ['exception' => $exception]);
        } finally {
            $this->progress->detach();
        }
        $state['updated_at'] = time();
        $this->storage->save($runId, $state);

        return $state;
    }
}
