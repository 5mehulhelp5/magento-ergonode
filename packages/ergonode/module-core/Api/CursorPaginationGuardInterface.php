<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface CursorPaginationGuardInterface
{
    public const int MAX_PAGES = 100;

    /**
     * @param array<string, mixed> $pageInfo
     * @return string|null
     * @throws LocalizedException
     */
    public function next(array $pageInfo): ?string;
}
