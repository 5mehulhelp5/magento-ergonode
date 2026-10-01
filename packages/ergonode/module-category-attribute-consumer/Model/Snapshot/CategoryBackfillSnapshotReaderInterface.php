<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Snapshot;

interface CategoryBackfillSnapshotReaderInterface
{
    /** @return list<array{category_code: string, attributes_json: string,
     *     magento_category_id: string|int, category_tree_id: string|int}>
     */
    public function getRows(): array;
}
