<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeSnapshotRefreshInterface
{
    /**
     * Reconcile on the first call and return a local snapshot page. Cursors are opaque, temporary browser cursors.
     * Later pages do not query Ergonode; a changed checkpoint requires restarting the refresh.
     * Magento mappings and options are not mutated.
     *
     * @param  string|null $cursor
     * @param  int|null    $pageSize
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     attribute_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function refreshSnapshot(?string $cursor = null, ?int $pageSize = null): array;
}
