<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationStateInterface;
use Magento\Framework\Exception\LocalizedException;

/** Shared within the executing request; idle for CLI, cron and mapping previews. */
class CategorySynchronizationProgress
{
    private ?string $runId = null;
    /** @var array<string, mixed> */
    private array $state = [];

    public function __construct(private readonly CategorySynchronizationStateInterface $storage)
    {
    }

    /** @param array<string, mixed> $state */
    public function attach(string $runId, array $state): void
    {
        $this->runId = $runId;
        $this->state = $state;
    }

    public function isManaged(): bool
    {
        return $this->runId !== null;
    }

    public function completedOperation(string $operation): void
    {
        if ($this->runId === null || $this->storage->get($this->runId) === null) {
            return;
        }
        $this->state['operations'][$operation] = (int)($this->state['operations'][$operation] ?? 0) + 1;
        $this->state['updated_at'] = time();
        // Record committed work before the next checkpoint can accept a pause.
        $this->storage->save($this->runId, $this->state);
    }

    public function detach(): void
    {
        $this->runId = null;
        $this->state = [];
    }

    public function checkpoint(string $stage, int $completed = 0, ?int $total = null, string $item = ''): void
    {
        if ($this->runId === null) {
            return;
        }
        if ($this->storage->get($this->runId) === null) {
            throw new LocalizedException(
                __('Synchronization progress expired. Check the saved changes before restarting.')
            );
        }
        if ($this->storage->isPauseRequested($this->runId)) {
            throw new CategorySynchronizationPaused();
        }
        $this->state['stage'] = $stage;
        $this->state['processed'] = $completed;
        $this->state['total'] = $total;
        $this->state['item'] = $item;
        $this->state['updated_at'] = time();
        $this->storage->save($this->runId, $this->state);
    }

    public function startTree(string $code, int $number, int $total): void
    {
        $this->state['tree_code'] = $code;
        $this->state['tree_number'] = $number;
        $this->state['tree_total'] = $total;
        $this->state['downloaded'] = 0;
        $this->state['pages'] = 0;
        $this->checkpoint('fetching_tree', item: $code);
    }

    public function downloadedPage(string $code, bool $first, int $categories): void
    {
        if ($this->runId === null) {
            return;
        }
        $this->state['tree_code'] = $code;
        $this->state['downloaded'] = ($first ? 0 : (int)($this->state['downloaded'] ?? 0)) + $categories;
        $this->state['pages'] = ($first ? 0 : (int)($this->state['pages'] ?? 0)) + 1;
        $this->checkpoint('fetching_tree', item: $code);
    }

    /** @return array<string, mixed>|null */
    public function getStageResult(string $stage): ?array
    {
        return $this->state['results'][$stage] ?? null;
    }

    /** @param array<string, mixed> $result */
    public function completeStage(string $stage, array $result): void
    {
        if ($this->runId !== null) {
            $this->state['results'][$stage] = $result;
            $this->storage->save($this->runId, $this->state);
        }
    }
}
