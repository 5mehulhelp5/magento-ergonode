<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface PageQueryRetrierInterface
{
    /**
     * @param int[] $pageSizes
     * @param int $requestedPageSize
     * @param callable(int): array<string, mixed> $query
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function query(array $pageSizes, int $requestedPageSize, callable $query): array;
}
