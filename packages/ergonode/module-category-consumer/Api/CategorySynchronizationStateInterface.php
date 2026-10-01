<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

/** Persistence port for the transient synchronization state and its independent pause flag. */
interface CategorySynchronizationStateInterface
{
    /**
     * @param string $runId
     * @return array<string, mixed>|null
     */
    public function get(string $runId): ?array;

    /**
     * @param string $runId
     * @param array<string, mixed> $state
     * @return void
     */
    public function save(string $runId, array $state): void;

    /**
     * @param string $runId
     * @return void
     */
    public function requestPause(string $runId): void;

    /**
     * @param string $runId
     * @return bool
     */
    public function isPauseRequested(string $runId): bool;

    /**
     * @param string $runId
     * @return void
     */
    public function clearPause(string $runId): void;
}
