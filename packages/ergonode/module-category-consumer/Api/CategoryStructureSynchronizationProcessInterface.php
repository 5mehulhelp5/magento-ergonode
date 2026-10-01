<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryStructureSynchronizationProcessInterface
{
    /**
     * Consume categoryTreeStream and reconcile active configured trees.
     * Reject unavailable synchronization before starting a run or touching its cursor.
     *
     * @param bool $resetCursor Reset the global stream cursor and reconcile every active configured tree.
     * @return array{
     *     events: int, trees: int, conflicts: int, cursor: string|null,
     *     tree_results: list<array<string, mixed>>
     * }
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(bool $resetCursor = false): array;
}
