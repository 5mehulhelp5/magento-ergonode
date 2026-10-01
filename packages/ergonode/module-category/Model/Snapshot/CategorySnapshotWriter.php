<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Snapshot;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;
use Zend_Db_Expr;

class CategorySnapshotWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    public function markAllUnseen(int $categoryTreeId): void
    {
        if ($categoryTreeId <= 0) {
            return;
        }

        $this->connection()->update(
            $this->table(),
            ['seen_at' => null],
            ['category_tree_id = ?' => $categoryTreeId]
        );
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     parent_code: string|null,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     raw: array<string, mixed>,
     *     hash: string
     * }> $categories
     * @return array{inserted: int, updated: int, unchanged: int, removed: int}
     * @throws Throwable
     */
    public function replaceCompleteSnapshot(int $categoryTreeId, array $categories): array
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $this->markAllUnseen($categoryTreeId);
            $results = $this->saveCategories($categoryTreeId, $categories);
            $removed = $categories === []
                ? $connection->delete($this->table(), ['category_tree_id = ?' => $categoryTreeId])
                : ($this->deleteUnseen($categoryTreeId) ?? 0);
            $stats = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => $removed];
            foreach ($results as $result) {
                $stats[$result]++;
            }
            $connection->commit();

            return $stats;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int, array{
     *     code: string,
     *     parent_code: string|null,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     raw: array<string, mixed>,
     *     hash: string
     * }> $categories
     * @return array<string, 'inserted'|'updated'|'unchanged'>
     */
    public function saveCategories(int $categoryTreeId, array $categories): array
    {
        if ($categoryTreeId <= 0 || $categories === []) {
            return [];
        }

        $connection = $this->connection();
        $table = $this->table();
        $existing = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($table, ['category_code', 'content_hash', 'sort_order'])
                ->where('category_tree_id = ?', $categoryTreeId)
                ->where('category_code IN (?)', array_values(array_unique(array_column($categories, 'code'))))
        ) as $row) {
            $existing[(string)$row['category_code']] = $row;
        }

        $rows = [];
        $results = [];
        foreach ($categories as $category) {
            $code = $category['code'];
            $current = $existing[$code] ?? null;
            $result = $current === null
                ? 'inserted'
                : (
                    (string)$current['content_hash'] === $category['hash']
                    && (int)$current['sort_order'] === $category['sort_order']
                        ? 'unchanged'
                        : 'updated'
                );
            $results[$code] = $result;
            if ($result === 'unchanged') {
                continue;
            }

            $rows[] = [
                'category_tree_id' => $categoryTreeId,
                'category_code' => $code,
                'parent_category_code' => $category['parent_code'],
                'sort_order' => $category['sort_order'],
                'labels_json' => $this->json->serialize($category['labels']),
                'content_hash' => $category['hash'],
                'raw_json' => $this->json->serialize($category['raw']),
            ];
        }
        if ($rows !== []) {
            $connection->insertOnDuplicate($table, $rows, [
                'parent_category_code',
                'sort_order',
                'labels_json',
                'content_hash',
                'raw_json',
            ]);
        }

        $connection->update(
            $table,
            ['seen_at' => new Zend_Db_Expr('CURRENT_TIMESTAMP')],
            [
                'category_tree_id = ?' => $categoryTreeId,
                'category_code IN (?)' => array_values(array_unique(array_column($categories, 'code'))),
            ]
        );

        return $results;
    }

    public function deleteUnseen(int $categoryTreeId): ?int
    {
        if ($categoryTreeId <= 0 || !$this->hasSeenCategories($categoryTreeId)) {
            return null;
        }

        return $this->connection()->delete($this->table(), [
            'category_tree_id = ?' => $categoryTreeId,
            'seen_at IS NULL',
        ]);
    }

    public function deleteCategory(int $categoryTreeId, string $categoryCode): int
    {
        $categoryCode = trim($categoryCode);
        if ($categoryTreeId <= 0 || $categoryCode === '') {
            return 0;
        }

        return $this->connection()->delete($this->table(), [
            'category_tree_id = ?' => $categoryTreeId,
            'category_code = ?' => $categoryCode,
        ]);
    }

    private function hasSeenCategories(int $categoryTreeId): bool
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()
                ->select()
                ->from($this->table(), ['count' => 'COUNT(*)'])
                ->where('category_tree_id = ?', $categoryTreeId)
                ->where('seen_at IS NOT NULL')
        ) > 0;
    }

    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName('ergonode_category_snapshot');
    }
}
