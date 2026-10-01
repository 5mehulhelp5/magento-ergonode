<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Api;

interface HistoryQueryInterface
{
    /**
     * @param int $limit
     * @param int|null $beforeOperationId
     * @return array{items: list<array<string, mixed>>, total: int, has_more: bool}
     */
    public function getOperations(int $limit = 10, ?int $beforeOperationId = null): array;

    /**
     * @param int $operationId
     * @return array{
     *     operation: array<string, mixed>,
     *     source: list<array<string, mixed>>,
     *     target: list<array<string, mixed>>,
     *     changes: list<array<string, mixed>>
     * }|null
     */
    public function getState(int $operationId): ?array;
}
