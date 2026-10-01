<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface MappingSynchronizationInterface
{
    /**
     * Save under the category lock and then backfill mapped values from local snapshots.
     * @param callable(): array<string, mixed> $save
     * @return array<string, mixed>
     */
    public function execute(callable $save): array;
}
