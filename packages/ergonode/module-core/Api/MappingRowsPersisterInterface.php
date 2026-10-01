<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\DB\Adapter\AdapterInterface;

interface MappingRowsPersisterInterface
{
    /**
     * @param AdapterInterface $connection
     * @param string $table
     * @param array<string, array<string, mixed>> $existing
     * @param array<string, array<string, mixed>> $normalized
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function persist(
        AdapterInterface $connection,
        string $table,
        array $existing,
        array $normalized
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    public function hash(array $payload): string;
}
