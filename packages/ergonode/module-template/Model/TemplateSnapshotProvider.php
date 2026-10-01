<?php

declare(strict_types=1);

namespace Ergonode\Template\Model;

use Ergonode\Template\Api\TemplateSnapshotProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class TemplateSnapshotProvider implements TemplateSnapshotProviderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getTemplates(): array
    {
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    ['template' => $this->resourceConnection->getTableName('ergonode_template')],
                    [
                        'entity_id',
                        'code',
                        'attribute_set_id',
                        'is_deleted',
                        'synced_at',
                        'updated_at',
                        'raw_json',
                    ]
                )
                ->joinLeft(
                    ['attribute_set' => $this->resourceConnection->getTableName('eav_attribute_set')],
                    'attribute_set.attribute_set_id = template.attribute_set_id',
                    ['attribute_set_name']
                )
                ->order('template.code ASC')
        );

        return $rows;
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
