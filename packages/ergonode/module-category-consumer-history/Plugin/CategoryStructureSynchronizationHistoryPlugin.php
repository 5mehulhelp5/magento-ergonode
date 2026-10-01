<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;

class CategoryStructureSynchronizationHistoryPlugin
{
    public function __construct(
        private readonly CategoryTreeHistoryCapture $historyCapture
    ) {
    }

    /** @return array{events: int, trees: int, conflicts: int, cursor: string|null} */
    public function aroundExecute(
        CategoryStructureSynchronizationProcessInterface $_subject,
        callable $proceed,
        bool $resetCursor = false
    ): array {
        return $this->historyCapture->executeGrouped(
            $resetCursor ? 'synchronize_reset' : 'synchronize',
            static fn (): array => $proceed($resetCursor),
            static fn (array $result): array => [
                'status' => (int)$result['conflicts'] > 0 ? 'warning' : 'success',
                'summary' => [
                    'events' => (int)$result['events'],
                    'trees' => (int)$result['trees'],
                    'conflicts' => (int)$result['conflicts'],
                    'cursor' => $result['cursor'],
                ],
            ]
        );
    }
}
