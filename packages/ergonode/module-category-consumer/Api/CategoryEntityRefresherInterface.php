<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryEntityRefresherInterface
{
    /**
     * Refresh one mapped Magento category from its current Ergonode entity.
     *
     * @param int $magentoCategoryId
     * @return array{
     *     code: string,
     *     snapshot: 'inserted'|'updated'|'unchanged',
     *     attributes: int
     * }
     */
    public function refresh(int $magentoCategoryId): array;
}
