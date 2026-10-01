<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\ResourceModel;

use Ergonode\ProductPublisher\Api\ProductPublicationResultWriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class ProductPublicationResultWriter implements ProductPublicationResultWriterInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function save(array $items): string
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($items === []) {
            return $now;
        }
        $connection = $this->resourceConnection->getConnection();
        try {
            $existing = $connection->fetchCol($connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('entity_id IN (?)', array_column($items, 'product_id')));
            $rows = [];
            foreach ($items as $item) {
                if (!in_array($item['product_id'], array_map('intval', $existing), true)) {
                    continue;
                }
                $rows[] = [
                    'product_id' => $item['product_id'],
                    'status' => $item['status'],
                    'message' => $item['message'],
                    'recorded_at' => $now,
                ];
            }
            if ($rows !== []) {
                $connection->insertOnDuplicate(
                    $this->resourceConnection->getTableName('ergonode_product_publication_result'),
                    $rows,
                    ['status', 'message', 'recorded_at']
                );
            }
        } catch (Throwable $exception) {
            throw new LocalizedException(__(
                'Unable to save the product publication result. Remote writes may already have completed; '
                    . 'verify the product before publishing again.'
            ), $exception);
        }

        return $now;
    }
}
