<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryDataSynchronizationProcessInterface
{
    /**
     * Consume categoryStream and synchronize names and optional mapped values.
     * Reject unavailable synchronization before starting a run or touching its cursor.
     *
     * @param bool $resetCursor
     *
     * @return array{
     *     events: int,
     *     fetched: int,
     *     snapshots: int,
     *     attributes: int,
     *     cursor: string|null
     * }
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(bool $resetCursor = false): array;
}
