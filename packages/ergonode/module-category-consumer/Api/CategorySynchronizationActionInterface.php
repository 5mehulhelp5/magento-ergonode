<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategorySynchronizationActionInterface
{
    /**
     * @param string $scope tree, data or all; every scope covers all active configured trees.
     * @param string $action sync, reset-cursor or reset-cursor-and-sync.
     * @return array{events: int, conflicts: int, results: array<string, array<string, mixed>>}
     */
    public function execute(string $scope, string $action): array;
}
