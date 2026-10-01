<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryTreeRefreshServiceInterface
{
    /**
     * @param int $categoryTreeId
     * @return array{
     *     complete: true,
     *     pages: int,
     *     page_size: int,
     *     categories: array<int, array<string, mixed>>,
     *     snapshot: array{inserted: int, updated: int, unchanged: int, removed: int}
     * }
     */
    public function refresh(int $categoryTreeId): array;
}
