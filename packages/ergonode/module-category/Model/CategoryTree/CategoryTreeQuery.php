<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\CategoryTree;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryTreeQuery
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    public function getById(int $categoryTreeId): array
    {
        return $this->getByField('category_tree_id', $categoryTreeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(bool $activeOnly = false): array
    {
        $select = $this->connection()->select()
            ->from($this->table())
            ->order('sort_order ASC')
            ->order('category_tree_id ASC');
        if ($activeOnly) {
            $select->where('is_active = ?', 1);
        }

        return array_map([$this, 'normalize'], $this->connection()->fetchAll($select));
    }

    /** @param string[] $treeCodes @return array<int, array<string, mixed>> */
    public function getSynchronizableByTreeCodes(array $treeCodes): array
    {
        $treeCodes = array_values(array_unique(array_filter(array_map('trim', $treeCodes))));
        if ($treeCodes === []) {
            return [];
        }
        $rows = $this->connection()->fetchAll(
            $this->connection()->select()
                ->from($this->table())
                ->where('tree_code IN (?)', $treeCodes)
                ->where('is_active = ?', 1)
                ->order('sort_order ASC')
                ->order('category_tree_id ASC')
        );

        return array_map([$this, 'normalize'], $rows);
    }

    public function getNextSortOrder(): int
    {
        $value = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), ['max_sort_order' => 'MAX(sort_order)'])
        );

        return $value === false || $value === null ? 0 : (int)$value + 1;
    }

    public function findIdByRootCategoryId(int $rootCategoryId): ?int
    {
        $value = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), ['category_tree_id'])
                ->where('root_category_id = ?', $rootCategoryId)
                ->limit(1)
        );

        return $value === false ? null : (int)$value;
    }

    /**
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    private function getByField(string $field, int|string $value): array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table())
                ->where($field . ' = ?', $value)
                ->limit(1)
        );
        if (!is_array($row)) {
            throw new NoSuchEntityException(__('Category Tree does not exist.'));
        }

        return $this->normalize($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        foreach (['category_tree_id', 'root_category_id', 'sort_order'] as $field) {
            $row[$field] = (int)$row[$field];
        }
        foreach (['is_active', 'remove_missing'] as $field) {
            $row[$field] = (bool)$row[$field];
        }

        return $row;
    }

    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName('ergonode_category_tree');
    }
}
