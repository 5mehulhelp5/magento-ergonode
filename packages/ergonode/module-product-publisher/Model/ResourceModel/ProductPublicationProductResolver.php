<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\ResourceModel;

use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Magento\Framework\App\ResourceConnection;

class ProductPublicationProductResolver implements ProductPublicationProductResolverInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getCurrentSkus(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }

        return $this->fetchProductSkus('entity_id IN (?)', $productIds);
    }

    /**
     * @param string[] $skus
     * @return array<int, string>
     */
    public function getProductSkusBySkus(array $skus): array
    {
        $normalized = [];
        foreach ($skus as $sku) {
            if (is_string($sku) && trim($sku) !== '') {
                $normalized[trim($sku)] = trim($sku);
            }
        }
        $skus = array_values($normalized);
        if ($skus === []) {
            return [];
        }

        return $this->fetchProductSkus('sku IN (?)', $skus);
    }

    /**
     * @param int[]|string[] $values
     * @return array<int, string>
     */
    private function fetchProductSkus(string $condition, array $values): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_entity'), ['entity_id', 'sku'])
                ->where($condition, $values)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['entity_id']] = (string)$row['sku'];
        }

        return $result;
    }
}
