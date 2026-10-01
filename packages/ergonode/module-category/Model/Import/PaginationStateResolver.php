<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class PaginationStateResolver
{
    /**
     * @param array<string, mixed> $pageInfo
     * @return array{cursor: string|null, has_more: bool}
     * @throws LocalizedException
     */
    public function resolve(
        array $pageInfo,
        ?string $currentCursor,
        Phrase $invalidPaginationMessage,
        ?string $fallbackCursor = null
    ): array {
        $hasMore = !empty($pageInfo['hasNextPage']);
        $nextCursor = trim((string)($pageInfo['endCursor'] ?? $fallbackCursor ?? '')) ?: null;
        if ($hasMore && ($nextCursor === null || $nextCursor === $currentCursor)) {
            throw new LocalizedException($invalidPaginationMessage);
        }

        return ['cursor' => $nextCursor, 'has_more' => $hasMore];
    }
}
