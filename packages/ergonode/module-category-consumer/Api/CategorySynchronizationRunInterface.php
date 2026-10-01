<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

/** Lifecycle of an operator-controlled category synchronization. */
interface CategorySynchronizationRunInterface
{
    /**
     * @param string $runId
     * @param string $scope
     * @param string $action
     * @param bool $resume
     * @return array<string, mixed>
     */
    public function execute(string $runId, string $scope, string $action, bool $resume = false): array;

    /**
     * @param string $runId
     * @return array<string, mixed>
     */
    public function getStatus(string $runId): array;

    /**
     * @param string $runId
     * @return void
     */
    public function pause(string $runId): void;
}
