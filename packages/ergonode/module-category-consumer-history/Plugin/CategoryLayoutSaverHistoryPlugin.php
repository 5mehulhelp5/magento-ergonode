<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\Category\Api\CategoryLayoutSaverInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;

class CategoryLayoutSaverHistoryPlugin
{
    public function __construct(private readonly CategoryTreeHistoryCapture $historyCapture)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $visibility
     * @return array{updated: int, unchanged: int, attribute_values: int}
     */
    public function aroundSave(
        CategoryLayoutSaverInterface $_subject,
        callable $proceed,
        int $categoryTreeId,
        array $items,
        array $visibility = []
    ): array {
        return $this->historyCapture->execute(
            'save',
            [$categoryTreeId],
            static fn (): array => $proceed($categoryTreeId, $items, $visibility),
            static fn (array $result): array => [
                'status' => 'success',
                'summary' => [
                    'updated' => (int)$result['updated'],
                    'unchanged' => (int)$result['unchanged'],
                    'attribute_values' => (int)$result['attribute_values'],
                ],
            ]
        );
    }
}
