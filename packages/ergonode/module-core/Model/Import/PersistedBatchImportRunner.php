<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Ergonode\Core\Api\PersistedBatchImportRunnerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class PersistedBatchImportRunner implements PersistedBatchImportRunnerInterface
{
    private const string LOCK_PREFIX = 'ergonode_import_';

    public function __construct(
        private readonly CursorStorage $cursorStorage,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function executeBatch(
        string $processCode,
        int $defaultPageSize,
        ?int $pageSize,
        callable $importBatch
    ): array {
        return $this->withProcessLock(
            $processCode,
            fn (): array => $this->importNextBatch($processCode, $defaultPageSize, $pageSize, $importBatch)
        );
    }

    public function executeUntilComplete(
        string $processCode,
        int $defaultPageSize,
        ?int $pageSize,
        int $maxBatches,
        callable $importBatch
    ): array {
        return $this->withProcessLock(
            $processCode,
            function () use ($processCode, $defaultPageSize, $pageSize, $maxBatches, $importBatch): array {
                $summary = [
                    'batches' => 0,
                    'imported' => 0,
                    'changed' => 0,
                    'unchanged' => 0,
                    'has_more' => false,
                ];

                do {
                    $batch = $this->importNextBatch(
                        $processCode,
                        $defaultPageSize,
                        $pageSize,
                        $importBatch
                    );
                    $summary['batches']++;
                    $summary['imported'] += (int)$batch['imported'];
                    $summary['changed'] += (int)$batch['changed'];
                    $summary['unchanged'] += (int)$batch['unchanged'];
                    $summary['has_more'] = (bool)$batch['has_more'];
                } while ($summary['has_more'] && $summary['batches'] < max(1, $maxBatches));

                return $summary;
            }
        );
    }

    public function reset(string $processCode): void
    {
        $this->withProcessLock($processCode, function () use ($processCode): void {
            $this->cursorStorage->reset($processCode);
        });
    }

    /**
     * @template TResult
     * @param callable(): TResult $operation
     * @return TResult
     * @throws LocalizedException
     */
    private function withProcessLock(string $processCode, callable $operation): mixed
    {
        $lockName = self::LOCK_PREFIX . sha1(trim($processCode));
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new LocalizedException(
                __('Ergonode import process "%1" is already running.', $processCode)
            );
        }

        try {
            return $operation();
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * @param callable(?string, int): array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int
     * } $importBatch
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int
     * }
     */
    private function importNextBatch(
        string $processCode,
        int $defaultPageSize,
        ?int $pageSize,
        callable $importBatch
    ): array {
        $state = $this->cursorStorage->get($processCode);
        $cursor = $state['cursor'] ?? null;
        $effectivePageSize = $pageSize !== null && $pageSize > 0
            ? $pageSize
            : max(1, $defaultPageSize);
        $result = $importBatch($cursor, $effectivePageSize);
        $nextCursor = trim((string)($result['cursor'] ?? ''));

        if (!(bool)$result['has_more']) {
            $completedCursor = $nextCursor !== '' ? $nextCursor : trim((string)$cursor);
            if ($completedCursor !== '') {
                $this->cursorStorage->save($processCode, $completedCursor);
            }

            return $result;
        }

        if ($nextCursor === '' || $nextCursor === (string)$cursor) {
            throw new LocalizedException(
                __('Ergonode import process "%1" did not advance its cursor.', $processCode)
            );
        }

        $this->cursorStorage->save($processCode, $nextCursor);

        return $result;
    }
}
