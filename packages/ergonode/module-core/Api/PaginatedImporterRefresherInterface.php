<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

use Magento\Framework\Exception\LocalizedException;

interface PaginatedImporterRefresherInterface
{
    /**
     * @param callable(?string): array<string, mixed> $importPage
     * @return void
     * @throws LocalizedException
     */
    public function refresh(callable $importPage): void;
}
