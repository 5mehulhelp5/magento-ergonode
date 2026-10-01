<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Snapshot;

use Ergonode\Category\Api\CategoryTreeSnapshotUpdaterInterface;
use Ergonode\Category\Model\Import\CategoryDetailsLoader;
use Ergonode\Category\Model\Import\CategoryNormalizer;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Magento\Framework\Exception\LocalizedException;

class CategoryTreeSnapshotUpdater implements CategoryTreeSnapshotUpdaterInterface
{
    public function __construct(
        private readonly CategoryCacheProvider $cacheProvider,
        private readonly CategoryDetailsLoader $detailsLoader,
        private readonly CategoryNormalizer $normalizer,
        private readonly CategorySnapshotWriter $snapshotWriter
    ) {
    }

    /**
     * @param array<int, array{code: string, parent_code: string|null}> $layout
     * @param array<string, array{code: string, name: array<int, array<string, mixed>>}> $confirmedCategories
     */
    public function update(int $categoryTreeId, array $layout, array $confirmedCategories): void
    {
        if ($categoryTreeId <= 0) {
            throw new LocalizedException(__('Category Tree is required.'));
        }
        $this->validateLayout($layout);
        $existing = $this->cacheProvider->getRowsByCode($categoryTreeId);
        $missing = array_values(array_diff(array_column($layout, 'code'), array_keys($existing)));
        $details = $this->confirmedDetails($missing, $confirmedCategories);
        $unconfirmed = array_values(array_diff($missing, array_keys($details)));
        if ($unconfirmed !== []) {
            $details += $this->detailsLoader->load($unconfirmed);
        }
        $categories = [];
        foreach ($layout as $item) {
            $code = $item['code'];
            $category = $details[$code] ?? [
                'code' => $code,
                'name' => $this->names($existing[$code]['labels']),
            ];
            $normalized = $this->normalizer->normalizeTreeNode([
                'category' => $category,
                'parentCategory' => $item['parent_code'] === null ? null : ['code' => $item['parent_code']],
            ], count($categories));
            if ($normalized !== null) {
                $categories[] = $normalized;
            }
        }
        $this->snapshotWriter->replaceCompleteSnapshot($categoryTreeId, $categories);
        $this->cacheProvider->clearCache();
    }

    /**
     * @param string[] $codes
     * @param array<string, array{code: string, name: array<int, array<string, mixed>>}> $categories
     * @return array<string, array{code: string, name: array<int, array<string, mixed>>}>
     */
    private function confirmedDetails(array $codes, array $categories): array
    {
        $details = [];
        foreach ($codes as $code) {
            $category = $categories[$code] ?? null;
            if (is_array($category) && ($category['code'] ?? null) === $code
                && is_array($category['name'] ?? null)
            ) {
                $details[$code] = $category;
            }
        }

        return $details;
    }

    /** @param array<int, array{code: string, parent_code: string|null}> $layout */
    private function validateLayout(array $layout): void
    {
        $seen = [];
        foreach ($layout as $item) {
            $code = $item['code'];
            $parent = $item['parent_code'];
            if ($code === '' || trim($code) !== $code || isset($seen[$code])
                || ($parent !== null && !isset($seen[$parent]))
            ) {
                throw new LocalizedException(__('The published category hierarchy is invalid. Refresh the tree.'));
            }
            $seen[$code] = true;
        }
    }

    /**
     * @param array<string, string> $labels
     * @return array<int, array{language: string, value: string}>
     */
    private function names(array $labels): array
    {
        $names = [];
        foreach ($labels as $language => $value) {
            $names[] = ['language' => $language, 'value' => $value];
        }

        return $names;
    }
}
