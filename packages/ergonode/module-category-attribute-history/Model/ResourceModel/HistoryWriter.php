<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model\ResourceModel;

use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class HistoryWriter implements HistoryWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    public function save(array $operation): void
    {
        $operation['state_json'] = $this->json->serialize($operation['state']);
        $operation['changes_json'] = $this->json->serialize($operation['changes']);
        unset($operation['state'], $operation['changes']);
        $this->resourceConnection->getConnection()->insert(
            $this->resourceConnection->getTableName('ergonode_category_attribute_history_operation'),
            $operation
        );
    }
}
