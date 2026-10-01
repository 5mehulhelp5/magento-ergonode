<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Provider;

use Ergonode\Category\Model\Provider\MagentoCategoryProvider;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;

class CategoryTreeStateProvider implements CategoryTreeStateProviderInterface
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    public function getTrees(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $tree): array => [
                'category_tree_id' => (int)$tree['category_tree_id'],
                'tree_code' => (string)$tree['tree_code'],
                'root_category_id' => (int)$tree['root_category_id'],
                'is_active' => (bool)$tree['is_active'],
            ],
            $this->categoryTreeQuery->getList($activeOnly)
        );
    }

    public function getState(int $categoryTreeId): array
    {
        $tree = $this->categoryTreeQuery->getById($categoryTreeId);
        $rootCategoryId = (int)$tree['root_category_id'];
        // History captures before and after mutations within the same process.
        $this->categoryCacheProvider->clearCache();
        $this->magentoCategoryProvider->clearCache();
        $sourceRows = $this->categoryCacheProvider->getRows($categoryTreeId);
        $targetRows = array_values($this->magentoCategoryProvider->getCategories($rootCategoryId));
        $sourceIdentifiers = array_map(
            static fn (array $row): string => (string)$row['code'],
            $sourceRows
        );
        $targetIdentifiers = array_map(
            static fn (array $row): string => (string)$row['id'],
            $targetRows
        );
        $sourceActive = $this->visibilityProvider->getActiveMap(
            'category',
            'ergo',
            $sourceIdentifiers,
            (string)$categoryTreeId
        );
        $targetActive = $this->visibilityProvider->getActiveMap(
            'category',
            'magento',
            $targetIdentifiers,
            (string)$categoryTreeId
        );
        $targetLabels = [];
        foreach ($targetRows as $row) {
            $targetLabels[(int)$row['id']] = (string)$row['label'];
        }

        $source = [];
        $mappingCodes = [];
        foreach ($sourceRows as $row) {
            $code = (string)$row['code'];
            $magentoCategoryId = $row['magento_category_id'] !== null
                ? (int)$row['magento_category_id']
                : null;
            if ($magentoCategoryId !== null && $magentoCategoryId > 0) {
                $mappingCodes[$magentoCategoryId] = $code;
            }
            $source[] = [
                'identifier' => $code,
                'label' => (string)$row['label'],
                'parent_identifier' => $this->nullableString($row['effective_parent_code']),
                'source_parent_identifier' => $this->nullableString($row['parent_code']),
                'sort_order' => (int)$row['effective_sort_order'],
                'source_sort_order' => (int)$row['sort_order'],
                'magento_category_id' => $magentoCategoryId,
                'magento_label' => $magentoCategoryId !== null
                    ? ($targetLabels[$magentoCategoryId] ?? null)
                    : null,
                'active' => $sourceActive[$code] ?? true,
            ];
        }

        $target = [];
        foreach ($targetRows as $row) {
            $identifier = (string)$row['id'];
            $target[] = [
                'identifier' => $identifier,
                'label' => (string)$row['label'],
                'parent_identifier' => (int)$row['id'] === $rootCategoryId
                    ? null
                    : (string)$row['parent_id'],
                'sort_order' => (int)$row['position'],
                'level' => (int)$row['level'],
                'path' => (string)$row['path'],
                'active' => $targetActive[$identifier] ?? true,
                'category_code' => $mappingCodes[(int)$row['id']] ?? null,
            ];
        }

        return [
            'tree' => [
                'category_tree_id' => $categoryTreeId,
                'tree_code' => (string)$tree['tree_code'],
                'root_category_id' => $rootCategoryId,
                'root_label' => $targetLabels[$rootCategoryId] ?? ('#' . $rootCategoryId),
                'is_active' => (bool)$tree['is_active'],
            ],
            'source' => $source,
            'target' => $target,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
