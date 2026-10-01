<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Api\CategoryCreationContextProviderInterface;
use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class CategoryCreationContextProvider implements CategoryCreationContextProviderInterface
{
    public function __construct(
        private readonly CategoryFormContextProviderInterface $formContextProvider,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getForMagentoCategory(int $categoryId): ?array
    {
        $formContext = $this->formContextProvider->getForMagentoCategory($categoryId);
        if ($formContext === null) {
            return null;
        }

        $category = $this->categoryRepository->get($categoryId, Store::DEFAULT_STORE_ID);
        $urlKey = $category->getCustomAttribute('url_key');
        $categoryTreeId = $formContext['category_tree_id'];
        $pathIds = array_values(array_filter(array_map(
            'intval',
            explode('/', (string)$category->getPath())
        )));

        return [
            'category_tree_id' => $categoryTreeId,
            'root_category_id' => $formContext['root_category_id'],
            'mapped_code' => $formContext['ergonode_category_code'],
            'pending_code' => $this->getPendingCode($categoryTreeId, $categoryId),
            'category' => [
                'id' => $categoryId,
                'label' => trim((string)$category->getName()),
                'url_key' => $urlKey !== null ? trim((string)$urlKey->getValue()) : '',
                'position' => max(0, (int)$category->getPosition()),
                'path_ids' => $pathIds,
                'path_labels' => $this->getPathLabels(
                    $pathIds,
                    $formContext['root_category_id'],
                    $category
                ),
            ],
            'items' => $this->getItems($categoryTreeId),
        ];
    }

    /**
     * @param int[] $pathIds
     * @return string[]
     */
    private function getPathLabels(array $pathIds, int $rootCategoryId, CategoryInterface $category): array
    {
        $rootOffset = array_search($rootCategoryId, $pathIds, true);
        $relativePathIds = $rootOffset === false
            ? [(int)$category->getId()]
            : array_slice($pathIds, $rootOffset + 1);
        $labels = [];

        foreach ($relativePathIds as $pathId) {
            $pathCategory = $pathId === (int)$category->getId()
                ? $category
                : $this->categoryRepository->get($pathId, Store::DEFAULT_STORE_ID);
            $label = trim((string)$pathCategory->getName());
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private function getPendingCode(int $categoryTreeId, int $categoryId): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $code): string => trim((string)$code),
            $connection->fetchCol(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('ergonode_category_mapping'),
                        ['ergonode_category_code']
                    )
                    ->where('category_tree_id = ?', $categoryTreeId)
                    ->where('magento_category_id = ?', $categoryId)
                    ->order('mapping_id ASC')
            )
        ), static fn (string $code): bool => $code !== '')));
        if (count($codes) > 1) {
            throw new LocalizedException(__(
                'Magento category ID "%1" has ambiguous Ergonode category mappings.',
                $categoryId
            ));
        }

        return $codes[0] ?? null;
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     label: string,
     *     parent_code: string|null,
     *     sort_order: int,
     *     magento_category_id: int|null
     * }>
     */
    private function getItems(int $categoryTreeId): array
    {
        return array_map(static fn (array $row): array => [
            'code' => (string)$row['code'],
            'label' => (string)$row['label'],
            'parent_code' => $row['effective_parent_code'],
            'sort_order' => (int)$row['effective_sort_order'],
            'magento_category_id' => $row['magento_category_id'],
        ], $this->categoryCacheProvider->getRows($categoryTreeId));
    }
}
