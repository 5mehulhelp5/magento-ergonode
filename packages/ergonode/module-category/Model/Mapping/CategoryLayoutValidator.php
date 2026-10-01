<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Mapping;

use Ergonode\Category\Api\CategoryLayoutValidatorInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Magento\Framework\Exception\LocalizedException;

class CategoryLayoutValidator implements CategoryLayoutValidatorInterface
{
    public function __construct(
        private readonly CategoryTreeQuery $treeQuery,
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategoryCacheProvider $cacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategoryMappingQuery $mappingQuery
    ) {
    }

    /**
     * Validate the resulting tree, including draft nodes and untouched mappings.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function validate(int $categoryTreeId, array $items): void
    {
        if ($categoryTreeId <= 0) {
            throw new LocalizedException(__('Category Tree is required.'));
        }
        $tree = $this->treeQuery->getById($categoryTreeId);
        $this->sourceState->assertCanUseSnapshot($categoryTreeId);
        $nodes = [];
        foreach ($this->mappingQuery->getPreparedLayoutByTreeId($categoryTreeId) as $row) {
            $nodes[(string)$row['code']] = [
                'parent_code' => $row['parent_code'],
                'magento_category_id' => (int)$row['magento_category_id'],
            ];
        }
        foreach ($this->cacheProvider->getRowsByCode($categoryTreeId) as $code => $row) {
            $nodes[$code] = [
                'parent_code' => $row['effective_parent_code'] ?? $row['parent_code'] ?? null,
                'magento_category_id' => (int)($row['magento_category_id'] ?? 0),
            ];
        }
        $seen = [];
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                throw new LocalizedException(__('Category codes must be non-empty and unique.'));
            }
            $seen[$code] = true;
            $parent = trim((string)($item['parent_code'] ?? ''));
            $nodes[$code] = [
                'parent_code' => $parent !== '' ? $parent : null,
                'magento_category_id' => (int)($item['magento_category_id'] ?? 0),
            ];
        }
        $this->validateMappings((int)$tree['root_category_id'], $nodes);
        $this->validateParents($nodes);
    }

    /** @param array<string, array{parent_code: string|null, magento_category_id: int}> $nodes */
    private function validateMappings(int $rootCategoryId, array $nodes): void
    {
        $used = [];
        $availableIds = null;
        foreach ($nodes as $node) {
            $id = $node['magento_category_id'];
            if ($id <= 0) {
                continue;
            }
            if (isset($used[$id])) {
                throw new LocalizedException(__('Magento category ID "%1" is mapped more than once.', $id));
            }
            $used[$id] = true;
            $availableIds ??= array_fill_keys($this->magentoCategoryProvider->getIds($rootCategoryId), true);
            if (!isset($availableIds[$id])) {
                throw new LocalizedException(__(
                    'Magento category ID "%1" does not exist in the configured root tree.',
                    $id
                ));
            }
        }
    }

    /** @param array<string, array{parent_code: string|null, magento_category_id: int}> $nodes */
    private function validateParents(array $nodes): void
    {
        $checked = [];
        foreach ($nodes as $code => $node) {
            $path = [];
            $current = (string)$code;
            while ($current !== null && !isset($checked[$current])) {
                if (isset($path[$current])) {
                    throw new LocalizedException(__('Category tree contains a cycle near "%1".', $code));
                }
                if (!isset($nodes[$current])) {
                    throw new LocalizedException(__('Invalid parent category selected for "%1".', $code));
                }
                $path[$current] = true;
                $current = $nodes[$current]['parent_code'];
            }
            $checked += $path;
        }
    }
}
