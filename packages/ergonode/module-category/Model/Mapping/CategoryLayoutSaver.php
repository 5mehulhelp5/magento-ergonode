<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Mapping;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Api\CategoryMappingSaveHandlerInterface;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class CategoryLayoutSaver
{
    public function __construct(
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly CategoryLayoutValidator $validator,
        private readonly LoggerInterface $logger,
        private readonly CategoryMappingWriter $categoryMappingWriter,
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryMappingVisibility $categoryMappingVisibility,
        private readonly CategoryMappingSaveHandlerInterface $dataWriter
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $visibility
     * @return array{updated: int, unchanged: int, attribute_values: int}
     * @throws LocalizedException
     */
    public function save(int $categoryTreeId, array $items, array $visibility = []): array
    {
        $this->validator->validate($categoryTreeId, $items);

        $categoryTree = $this->categoryTreeQuery->getById($categoryTreeId);
        $rootCategoryId = (int)$categoryTree['root_category_id'];
        $existing = $this->categoryCacheProvider->getRowsByCode($categoryTreeId);
        $normalized = $this->normalize($items, $existing);
        $operations = $this->prepareNewMappings($normalized, $existing);

        $stats = [
            'updated' => 0,
            'unchanged' => 0,
            'attribute_values' => 0,
        ];

        try {
            $stats = $this->dataWriter->save(
                $categoryTreeId,
                function () use ($categoryTreeId, $normalized, $existing, $visibility, $stats): array {
                    foreach ($normalized as $code => $item) {
                        $current = $existing[$code];
                        if ($this->isUnchanged($current, $item)) {
                            $stats['unchanged']++;
                            continue;
                        }

                        $this->categoryMappingWriter->saveLayout(
                            $categoryTreeId,
                            (string)$code,
                            $item['manual_parent_code'],
                            $item['manual_sort_order'],
                            $item['magento_category_id']
                        );
                        $stats['updated']++;
                    }
                    $this->categoryMappingVisibility->save($categoryTreeId, $visibility);
                    return $stats;
                },
                $operations
            );
            $this->categoryCacheProvider->clearCache();
        } catch (Throwable $exception) {
            $this->logger->error('Unable to save Ergonode category layout.', [
                'category_tree_id' => $categoryTreeId,
                'tree_code' => (string)$categoryTree['tree_code'],
                'root_category_id' => $rootCategoryId,
                'exception' => $exception,
            ]);

            if ($exception instanceof LocalizedException) {
                throw $exception;
            }

            throw new LocalizedException(__('Unable to save category layout.'));
        }

        return $stats;
    }

    /**
     * @param array<string, array{magento_category_id: int|null}> $items
     * @param array<string, array<string, mixed>> $existing
     * @return array<string, int>
     */
    private function prepareNewMappings(array $items, array $existing): array
    {
        $mappings = [];
        foreach ($items as $code => $item) {
            $categoryId = (int)($item['magento_category_id'] ?? 0);
            if ($categoryId > 0 && $categoryId !== (int)($existing[$code]['magento_category_id'] ?? 0)) {
                $mappings[(string)$code] = $categoryId;
            }
        }

        return $mappings;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $existing
     * @return array<string, array{
     *     manual_parent_code: string|null,
     *     manual_sort_order: int|null,
     *     magento_category_id: int|null
     * }>
     * @throws LocalizedException
     */
    private function normalize(array $items, array $existing): array
    {
        $result = [];

        foreach ($items as $index => $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '' || !isset($existing[$code])) {
                continue;
            }

            $parentCode = trim((string)($item['parent_code'] ?? ''));
            if ($parentCode === '') {
                $parentCode = null;
            }
            $magentoCategoryId = (int)($item['magento_category_id'] ?? 0);
            $sortOrder = array_key_exists('sort_order', $item) ? max(0, (int)$item['sort_order']) : $index;
            $result[$code] = [
                'manual_parent_code' => $parentCode,
                'manual_sort_order' => $sortOrder,
                'magento_category_id' => $magentoCategoryId > 0 ? $magentoCategoryId : null,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $current
     * @param array{
     *     manual_parent_code: string|null,
     *     manual_sort_order: int|null,
     *     magento_category_id: int|null
     * } $incoming
     */
    private function isUnchanged(array $current, array $incoming): bool
    {
        $sameParent = (string)($current['manual_parent_code'] ?? '')
            === (string)($incoming['manual_parent_code'] ?? '');
        $sameOrder = (string)($current['manual_sort_order'] ?? '')
            === (string)($incoming['manual_sort_order'] ?? '');
        $sameCategory = (string)($current['magento_category_id'] ?? '')
            === (string)($incoming['magento_category_id'] ?? '');

        return $sameParent && $sameOrder && $sameCategory;
    }
}
