<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;

class CategoryTreeRefreshHistoryPlugin
{
    public function __construct(private readonly CategoryTreeHistoryCapture $historyCapture)
    {
    }

    /** @return array<string, mixed> */
    public function aroundRefresh(
        CategoryTreeRefreshServiceInterface $_subject,
        callable $proceed,
        int $categoryTreeId
    ): array {
        return $this->historyCapture->execute(
            'refresh_snapshot',
            [$categoryTreeId],
            static fn (): array => $proceed($categoryTreeId),
            static fn (array $result): array => [
                'status' => 'success',
                'summary' => [
                    'pages' => (int)($result['pages'] ?? 0),
                    'categories' => count($result['categories'] ?? []),
                    'inserted' => (int)($result['snapshot']['inserted'] ?? 0),
                    'updated' => (int)($result['snapshot']['updated'] ?? 0),
                    'unchanged' => (int)($result['snapshot']['unchanged'] ?? 0),
                    'removed' => (int)($result['snapshot']['removed'] ?? 0),
                ],
            ]
        );
    }
}
