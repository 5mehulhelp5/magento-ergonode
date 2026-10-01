<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\CategoryTree;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class CategoryTreeRepository
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CategoryTreeValidator $validator,
        private readonly CategoryTreeQuery $categoryTreeQuery
    ) {
    }

    /**
     * @param array<string, mixed> $categoryTree
     * @throws LocalizedException
     */
    public function save(array $categoryTree): int
    {
        $categoryTree = $this->preserveScope($categoryTree);
        $categoryTree = $this->normalize($categoryTree);
        $this->validator->validate($categoryTree);

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_tree');
        $categoryTreeId = (int)($categoryTree['category_tree_id'] ?? 0);
        unset($categoryTree['category_tree_id']);

        if ($categoryTreeId > 0) {
            $connection->update($table, $categoryTree, ['category_tree_id = ?' => $categoryTreeId]);

            return $categoryTreeId;
        }

        $connection->insert($table, $categoryTree);

        // AdapterInterface does not expose the connection-local lastInsertId() implementation.
        // phpcs:ignore VendivoMagento2.PHP.Behavior.RawSql -- Required connection-local identity read after insert.
        $categoryTreeId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
        return $categoryTreeId;
    }

    /**
     * @param array<string, mixed> $categoryTree
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function preserveScope(array $categoryTree): array
    {
        $categoryTreeId = (int)($categoryTree['category_tree_id'] ?? 0);
        if ($categoryTreeId <= 0) {
            return $categoryTree;
        }

        $existing = $this->categoryTreeQuery->getById($categoryTreeId);
        $submittedTreeCode = trim((string)($categoryTree['tree_code'] ?? $existing['tree_code']));
        $submittedRootCategoryId = (int)($categoryTree['root_category_id'] ?? $existing['root_category_id']);
        if ($submittedTreeCode !== (string)$existing['tree_code']
            || $submittedRootCategoryId !== (int)$existing['root_category_id']
        ) {
            throw new LocalizedException(
                __(
                    'The Ergonode category tree and Magento category root cannot be changed ' .
                    'after saving the category tree configuration.'
                )
            );
        }

        $categoryTree['tree_code'] = $existing['tree_code'];
        $categoryTree['root_category_id'] = $existing['root_category_id'];
        $categoryTree['sort_order'] = $existing['sort_order'];

        return $categoryTree;
    }

    public function deleteById(int $categoryTreeId): void
    {
        if ($categoryTreeId > 0) {
            $this->resourceConnection->getConnection()->delete(
                $this->resourceConnection->getTableName('ergonode_category_tree'),
                ['category_tree_id = ?' => $categoryTreeId]
            );
        }
    }

    /**
     * @param list<int> $categoryTreeIds
     * @throws LocalizedException
     */
    public function reorder(array $categoryTreeIds): void
    {
        $categoryTreeIds = array_values(array_map('intval', $categoryTreeIds));
        if (in_array(0, $categoryTreeIds, true)
            || count($categoryTreeIds) !== count(array_unique($categoryTreeIds))
        ) {
            throw new LocalizedException(__('The Category Tree order is invalid.'));
        }

        $existingIds = array_map(
            static fn (array $categoryTree): int => (int)$categoryTree['category_tree_id'],
            $this->categoryTreeQuery->getList()
        );
        $submittedIds = $categoryTreeIds;
        sort($existingIds);
        sort($submittedIds);
        if ($submittedIds !== $existingIds) {
            throw new LocalizedException(
                __('The Category Tree order must contain every configured tree exactly once.')
            );
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ergonode_category_tree');
        $connection->beginTransaction();
        try {
            foreach ($categoryTreeIds as $sortOrder => $categoryTreeId) {
                $connection->update(
                    $table,
                    ['sort_order' => $sortOrder],
                    ['category_tree_id = ?' => $categoryTreeId]
                );
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $categoryTree
     * @return array<string, mixed>
     */
    private function normalize(array $categoryTree): array
    {
        $categoryTreeId = (int)($categoryTree['category_tree_id'] ?? 0);
        $result = [
            'is_active' => !empty($categoryTree['is_active']) ? 1 : 0,
            'tree_code' => trim((string)($categoryTree['tree_code'] ?? '')),
            'root_category_id' => (int)($categoryTree['root_category_id'] ?? 0),
            'sort_order' => array_key_exists('sort_order', $categoryTree)
                ? max(0, (int)$categoryTree['sort_order'])
                : $this->categoryTreeQuery->getNextSortOrder(),
            'remove_missing' => !empty($categoryTree['remove_missing']) ? 1 : 0,
        ];
        if ($categoryTreeId > 0) {
            $result['category_tree_id'] = $categoryTreeId;
        }

        return $result;
    }
}
