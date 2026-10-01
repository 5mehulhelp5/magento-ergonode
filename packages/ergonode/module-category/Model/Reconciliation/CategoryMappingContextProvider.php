<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;

class CategoryMappingContextProvider
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly CategoryMappingQuery $categoryMappingQuery,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    /**
     * @param array<int, array{source: string, identifier: string, active: bool}> $draftVisibility
     * @param array<string, mixed>|null $tree
     * @return array{
     *     tree: array<string, mixed>, sources: array<int, array<string, mixed>>,
     *     magento: array<int, array<string, mixed>>, database_mappings: array<string, int>
     * }
     */
    public function get(int $categoryTreeId, array $draftVisibility = [], ?array $tree = null): array
    {
        $tree ??= $this->categoryTreeQuery->getById($categoryTreeId);
        $rows = $this->categoryCacheProvider->getRows($categoryTreeId);
        $magento = $this->magentoCategoryProvider->getCategories((int)$tree['root_category_id']);
        $scope = (string)$categoryTreeId;
        $sourceActive = $this->visibilityProvider->getActiveMap(
            'category',
            'ergo',
            array_map(static fn (array $row): string => (string)$row['code'], $rows),
            $scope
        );
        $magentoActive = $this->visibilityProvider->getActiveMap(
            'category',
            'magento',
            array_map(static fn (array $category): string => (string)$category['id'], $magento),
            $scope
        );
        foreach ($draftVisibility as $visibility) {
            $source = (string)($visibility['source'] ?? '');
            $identifier = (string)($visibility['identifier'] ?? '');
            if ($source === 'ergo' && $identifier !== '') {
                $sourceActive[$identifier] = !empty($visibility['active']);
            } elseif ($source === 'magento' && $identifier !== '') {
                $magentoActive[$identifier] = !empty($visibility['active']);
            }
        }

        $sources = [];
        foreach ($rows as $row) {
            $code = (string)$row['code'];
            $row['source_parent_code'] = $row['parent_code'] ?? null;
            $row['source_sort_order'] = (int)$row['sort_order'];
            $row['parent_code'] = $row['effective_parent_code'] ?? $row['source_parent_code'];
            $row['sort_order'] = (int)($row['effective_sort_order'] ?? $row['source_sort_order']);
            $row['active'] = $sourceActive[$code] ?? true;
            if ((array)($row['labels'] ?? []) === []) {
                $row['label'] = '';
            }
            $sources[] = $row;
        }
        foreach ($magento as $categoryId => &$category) {
            $category['active'] = $magentoActive[(string)$categoryId] ?? true;
        }
        unset($category);

        return [
            'tree' => $tree,
            'sources' => $sources,
            'magento' => $magento,
            'database_mappings' => $this->categoryMappingQuery->getMappingsByTreeId($categoryTreeId),
        ];
    }
}
