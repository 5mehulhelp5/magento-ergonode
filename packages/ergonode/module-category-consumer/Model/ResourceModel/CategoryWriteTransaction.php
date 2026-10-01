<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\ResourceModel;

use Ergonode\CategoryConsumer\Api\CategoryWriteTransactionInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CategoryWriteTransaction implements CategoryWriteTransactionInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function execute(callable $operation): mixed
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
