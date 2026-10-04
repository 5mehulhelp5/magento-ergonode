<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/** Last state reflected by cache invalidation; no work queue, scheduler or repair action. */
class ProductCacheState
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /** @param array<int, string> $initial @return array<int, string> */
    public function baseline(array $initial): array
    {
        if ($initial === []) { return []; }
        $db = $this->resource->getConnection();
        $table = $this->resource->getTableName('ergonode_product_cache_state');
        $rows = [];
        foreach ($initial as $id => $hash) { $rows[] = ['product_id' => $id, 'state_hash' => $hash]; }
        // An interrupted/failed earlier pass retains its original cache baseline.
        $db->insertOnDuplicate($table, $rows, ['product_id']);
        return $db->fetchPairs($db->select()->from($table, ['product_id', 'state_hash'])
            ->where('product_id IN (?)', array_keys($initial)));
    }

    /** Call only after successful selective invalidation. @param array<int, string> $hashes */
    public function save(array $hashes): void
    {
        if ($hashes === []) { return; }
        $rows = [];
        foreach ($hashes as $id => $hash) { $rows[] = ['product_id' => $id, 'state_hash' => $hash]; }
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName('ergonode_product_cache_state'), $rows, ['state_hash']
        );
    }
}
