<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

/**
 * @phpstan-type RemoteIdentity array{
 *     code: string,
 *     remote_id: string,
 *     manual_parent_code: string|null,
 *     manual_sort_order: int|null,
 *     magento_category_id: int|null
 * }
 */
interface CategoryRemoteIdentityWriterInterface
{
    /**
     * @param int $categoryTreeId
     * @param RemoteIdentity[] $identities
     * @return void
     */
    public function saveRemoteIdentities(int $categoryTreeId, array $identities): void;
}
