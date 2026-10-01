<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

use Ergonode\Category\Api\CategoryAutoMapperInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Exception\LocalizedException;

class CategoryAutoMapper implements CategoryAutoMapperInterface
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategoryMappingContextProvider $contextProvider,
        private readonly CategoryIdentityResolver $identityResolver,
        private readonly CategorySynchronizationLock $synchronizationLock
    ) {
    }

    public function suggest(int $categoryTreeId, array $draftMappings = [], array $draftVisibility = []): array
    {
        return $this->synchronizationLock->execute(
            fn (): array => $this->suggestLocked($categoryTreeId, $draftMappings, $draftVisibility)
        );
    }

    /**
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draftMappings
     * @param array<int, array{source: string, identifier: string, active: bool}> $draftVisibility
     * @return array<string, mixed>
     */
    private function suggestLocked(int $categoryTreeId, array $draftMappings, array $draftVisibility): array
    {
        $tree = $this->categoryTreeQuery->getById($categoryTreeId);
        if (empty($tree['is_active'])) {
            throw new LocalizedException(__('Category Tree is inactive and cannot be synchronized.'));
        }
        $this->sourceState->assertCanUseSnapshot($categoryTreeId);
        $this->categoryCacheProvider->clearCache();
        $this->magentoCategoryProvider->clearCache();
        $context = $this->contextProvider->get($categoryTreeId, $draftVisibility, $tree);
        $resolution = $this->identityResolver->resolve(
            (int)$tree['root_category_id'],
            $context['sources'],
            $context['magento'],
            $context['database_mappings'],
            $draftMappings
        );

        $stats = [
            'database' => 0, 'draft' => 0, 'name' => 0, 'unmatched' => 0,
            'moved' => 0, 'created' => 0, 'updated' => 0, 'deleted' => 0,
            'delete_candidates' => count($resolution['delete_candidates']),
        ];
        $categories = [];
        foreach ($context['sources'] as $source) {
            $assignment = $resolution['assignments'][(string)$source['code']] ?? [
                'magento_category_id' => null, 'source' => 'unmatched', 'expected_parent_id' => null,
            ];
            if (isset($stats[$assignment['source']])) {
                $stats[$assignment['source']]++;
            }
            $source['magento_category_id'] = $assignment['magento_category_id'];
            $source['mapping_source'] = $assignment['source'];
            $source['expected_parent_id'] = $assignment['expected_parent_id'];
            $categories[] = $source;
        }
        $consumed = array_fill_keys($resolution['consumed_magento_ids'], true);
        $protected = array_fill_keys($resolution['protected_magento_ids'], true);
        $magento = [];
        foreach ($context['magento'] as $categoryId => $category) {
            $category['consumed'] = isset($consumed[$categoryId]);
            $category['protected'] = isset($protected[$categoryId]);
            $magento[] = $category;
        }

        return [
            'categories' => $categories,
            'magento_categories' => $magento,
            'conflicts' => $resolution['conflicts'],
            'stats' => $stats,
            'delete_candidates' => $resolution['delete_candidates'],
        ];
    }
}
