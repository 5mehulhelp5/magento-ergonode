<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/** Read-only projection of the content expected at published media paths. */
class MaterializationAudit
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /** @return list<array{id:int,path:string,content_hash:string,asset_id:int,source_path:string,product_id:?int}> */
    public function page(int $afterId, int $limit = 200): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll($connection->select()->from(
            ['m' => $this->resource->getTableName('ergonode_media_materialization')],
            ['id' => 'materialization_id', 'path' => 'local_path', 'content_hash', 'asset_id', 'product_id']
        )->joinInner(
            ['a' => $this->resource->getTableName('ergonode_media_asset')],
            'a.asset_id = m.asset_id',
            ['source_path']
        )->where('m.materialization_id > ?', $afterId)->order('m.materialization_id ASC')->limit(max(1, min(1000, $limit))));

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'], 'path' => (string)$row['path'],
            'content_hash' => (string)$row['content_hash'], 'asset_id' => (int)$row['asset_id'],
            'source_path' => (string)$row['source_path'],
            'product_id' => $row['product_id'] === null ? null : (int)$row['product_id'],
        ], $rows);
    }
}
