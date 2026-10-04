<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Port;

use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;

interface ProductImportWorkRepositoryInterface
{
    /** @param array<int, array{sku: string, payload: array<string, mixed>}> $items */
    public function scheduleSynchronizations(array $items): int;

    /** @param array<int, array{sku: string, payload: null}> $items */
    public function scheduleDeletions(array $items): int;

    public function countActive(): int;

    /** @return ProductImportWorkItem[] */
    public function claim(int $limit, int $leaseSeconds): array;

    public function complete(ProductImportWorkItem $item): bool;

    public function release(
        ProductImportWorkItem $item,
        string $message,
        int $maximumAttempts,
        int $delaySeconds,
        bool $dependencyWait = false
    ): void;

    public function hasClaimableWork(): bool;

}
