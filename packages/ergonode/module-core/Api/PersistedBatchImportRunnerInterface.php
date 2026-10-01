<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

/**
 * @phpstan-type BatchImportResult array{
 *     has_more: bool,
 *     cursor: string|null,
 *     page_size: int,
 *     imported: int,
 *     changed: int,
 *     unchanged: int
 * }
 */
interface PersistedBatchImportRunnerInterface
{
    /**
     * @param string $processCode
     * @param int $defaultPageSize
     * @param int|null $pageSize
     * @param callable(?string, int): BatchImportResult $importBatch
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int
     * }
     */
    public function executeBatch(
        string $processCode,
        int $defaultPageSize,
        ?int $pageSize,
        callable $importBatch
    ): array;

    /**
     * @param string $processCode
     * @param int $defaultPageSize
     * @param int|null $pageSize
     * @param int $maxBatches
     * @param callable(?string, int): BatchImportResult $importBatch
     * @return array{batches: int, imported: int, changed: int, unchanged: int, has_more: bool}
     */
    public function executeUntilComplete(
        string $processCode,
        int $defaultPageSize,
        ?int $pageSize,
        int $maxBatches,
        callable $importBatch
    ): array;

    /**
     * @param string $processCode
     * @return void
     */
    public function reset(string $processCode): void;
}
