<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryEntitySynchronizerInterface
{
    /**
     * @param array<int, array{category_id: int, entity: array<string, mixed>}> $operations
     * @return array{
     *     snapshots: int,
     *     snapshot_statuses: array<string, 'inserted'|'updated'|'unchanged'>,
     *     attributes: int,
     *     changed_category_ids: int[]
     * }
     */
    public function synchronize(array $operations): array;
}
