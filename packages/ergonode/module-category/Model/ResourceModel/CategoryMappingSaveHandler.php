<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\ResourceModel;

use Ergonode\Category\Api\CategoryMappingSaveHandlerInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CategoryMappingSaveHandler implements CategoryMappingSaveHandlerInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function save(int $categoryTreeId, callable $saveLayout, array $newMappings): array
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $result = $saveLayout();
            $connection->commit();
            return $result;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
