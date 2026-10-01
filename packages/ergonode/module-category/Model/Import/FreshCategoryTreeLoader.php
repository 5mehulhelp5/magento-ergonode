<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;

use Magento\Framework\Exception\LocalizedException;
use Throwable;

class FreshCategoryTreeLoader
{
    public function __construct(
        private readonly CategoryTreeDownloader $downloader,
        private readonly CategorySnapshotWriter $categorySnapshotWriter,
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategoryMappingQuery $mappingQuery
    ) {
    }

    /**
     * @return array{
     *     complete: true,
     *     pages: int,
     *     page_size: int,
     *     categories: array<int, array{
     *         code: string,
     *         parent_code: string|null,
     *         labels: array<string, string>,
     *         sort_order: int,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>,
     *     snapshot: array{inserted: int, updated: int, unchanged: int, removed: int}
     * }
     * @throws LocalizedException
     * @throws Throwable
     */
    public function load(int $categoryTreeId): array
    {
        return $this->loadWith($categoryTreeId, false);
    }

    /**
     * Refresh the snapshot with the credential used by a write operation.
     *
     * @return array{
     *     complete: true,
     *     pages: int,
     *     page_size: int,
     *     categories: array<int, array{
     *         code: string,
     *         parent_code: string|null,
     *         labels: array<string, string>,
     *         sort_order: int,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>,
     *     snapshot: array{inserted: int, updated: int, unchanged: int, removed: int}
     * }
     * @throws LocalizedException|Throwable
     */
    public function loadWriteScope(int $categoryTreeId): array
    {
        return $this->loadWith($categoryTreeId, true);
    }

    /**
     * @return array{
     *     complete: true,
     *     pages: int,
     *     page_size: int,
     *     categories: array<int, array{
     *         code: string,
     *         parent_code: string|null,
     *         labels: array<string, string>,
     *         sort_order: int,
     *         raw: array<string, mixed>,
     *         hash: string
     *     }>,
     *     snapshot: array{inserted: int, updated: int, unchanged: int, removed: int}
     * }
     * @throws LocalizedException
     * @throws Throwable
     */
    private function loadWith(int $categoryTreeId, bool $writeScope): array
    {
        if ($categoryTreeId <= 0) {
            throw new LocalizedException(__('Category Tree is required.'));
        }
        $tree = $this->categoryTreeQuery->getById($categoryTreeId);
        if (empty($tree['is_active'])) {
            throw new LocalizedException(__('Category Tree is inactive and cannot be synchronized.'));
        }
        try {
            $result = $this->downloader->download((string)$tree['tree_code'], $writeScope);
            $result['snapshot'] = $this->categorySnapshotWriter->replaceCompleteSnapshot(
                $categoryTreeId,
                $result['categories']
            );
        } catch (MissingCategoryTreeException $exception) {
            $this->sourceState->record($categoryTreeId, CategoryTreeSourceState::MISSING);
            throw $exception;
        } catch (Throwable $exception) {
            $this->sourceState->record($categoryTreeId, CategoryTreeSourceState::UNAVAILABLE);
            throw $exception;
        }
        $missingCodes = array_values(array_diff(
            array_keys($this->mappingQuery->getMappingsByTreeId($categoryTreeId)),
            array_column($result['categories'], 'code')
        ));
        $this->sourceState->record($categoryTreeId, CategoryTreeSourceState::AVAILABLE, true, $missingCodes);

        return $result;
    }
}
