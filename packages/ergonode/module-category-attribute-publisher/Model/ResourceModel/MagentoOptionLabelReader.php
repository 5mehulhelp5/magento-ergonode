<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\ResourceModel;

use Ergonode\CategoryAttributePublisher\Api\MagentoOptionLabelReaderInterface;
use Magento\Framework\App\ResourceConnection;

class MagentoOptionLabelReader implements MagentoOptionLabelReaderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function read(int $attributeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll($connection->select()
            ->from(['option' => $this->resourceConnection->getTableName('eav_attribute_option')], ['option_id'])
            ->join(
                ['label' => $this->resourceConnection->getTableName('eav_attribute_option_value')],
                'label.option_id = option.option_id',
                ['store_id', 'value']
            )->where('option.attribute_id = ?', $attributeId));
        $labels = [];
        foreach ($rows as $row) {
            $labels[(int)$row['option_id']][(int)$row['store_id']] = (string)$row['value'];
        }

        return $labels;
    }
}
