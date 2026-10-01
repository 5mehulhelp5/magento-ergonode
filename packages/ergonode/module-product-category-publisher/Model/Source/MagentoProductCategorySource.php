<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Source;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;

class MagentoProductCategorySource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CategoryMappingProviderInterface $categoryMappingProvider
    ) {
    }

    /**
     * @param array<string, Product> $products
     * @return array<string, array{codes: string[], authoritative: bool}>
     */
    public function load(array $products): array
    {
        $productIds = array_values(array_filter(array_map(
            static fn (Product $product): int => (int)$product->getId(),
            $products
        )));
        $categoryIdsByProduct = [];
        if ($productIds !== []) {
            $connection = $this->resourceConnection->getConnection();
            foreach ($connection->fetchAll(
                $connection->select()
                    ->from(
                        $this->resourceConnection->getTableName('catalog_category_product'),
                        ['product_id', 'category_id']
                    )
                    ->where('product_id IN (?)', $productIds)
                    ->order(['product_id ASC', 'position ASC', 'category_id ASC'])
            ) as $row) {
                $categoryIdsByProduct[(int)$row['product_id']][] = (int)$row['category_id'];
            }
        }
        $allCategoryIds = [];
        foreach ($categoryIdsByProduct as $categoryIds) {
            $allCategoryIds = [...$allCategoryIds, ...$categoryIds];
        }
        $codesById = $this->categoryMappingProvider->getCategoryCodesByMagentoIds(
            array_values(array_unique($allCategoryIds))
        );
        $result = [];
        foreach ($products as $sku => $product) {
            $codes = [];
            $authoritative = true;
            foreach ($categoryIdsByProduct[(int)$product->getId()] ?? [] as $categoryId) {
                if (!isset($codesById[$categoryId])) {
                    $authoritative = false;
                    continue;
                }
                $codes[] = $codesById[$categoryId];
            }
            $result[$sku] = ['codes' => $codes, 'authoritative' => $authoritative];
        }

        return $result;
    }
}
