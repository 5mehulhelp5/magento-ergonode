<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;

class CategorySnapshotRemoverHistoryPlugin
{
    public function __construct(private readonly CategoryTreeHistoryCapture $historyCapture)
    {
    }

    public function aroundRemove(
        CategorySnapshotRemoverInterface $_subject,
        callable $proceed,
        int $categoryTreeId,
        string $categoryCode
    ): void {
        $this->historyCapture->execute(
            'remove_snapshot',
            [$categoryTreeId],
            static function () use ($proceed, $categoryTreeId, $categoryCode): void {
                $proceed($categoryTreeId, $categoryCode);
            },
            static fn (): array => ['status' => 'success', 'summary' => ['removed' => 1]]
        );
    }
}
