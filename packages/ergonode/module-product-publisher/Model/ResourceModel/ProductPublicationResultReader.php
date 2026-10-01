<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class ProductPublicationResultReader
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * @param int[] $productIds
     * @return array<int, string> UTC recorded time indexed by Magento product ID.
     */
    public function getUnconfirmedTimes(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll($connection->select()
            ->from(
                $this->resourceConnection->getTableName('ergonode_product_publication_result'),
                ['product_id', 'recorded_at']
            )
            ->where('product_id IN (?)', $productIds)
            ->where('status = ?', 'unconfirmed'));
        $times = [];
        foreach ($rows as $row) {
            $times[(int)$row['product_id']] = (string)$row['recorded_at'];
        }

        return $times;
    }
}
