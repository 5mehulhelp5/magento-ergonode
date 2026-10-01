<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\Import;

use Ergonode\Core\Api\SynchronizationStatusProviderInterface;
use InvalidArgumentException;

class SynchronizationStatusProvider implements SynchronizationStatusProviderInterface
{
    /**
     * @param array<string, array{
     *     label: string,
     *     description: string,
     *     sort_order?: int|numeric-string,
     *     monitor_only?: bool
     * }> $synchronizations
     */
    public function __construct(
        private readonly CursorStorage $cursorStorage,
        private readonly array $synchronizations = []
    ) {
    }

    public function getList(): array
    {
        $items = [];

        foreach ($this->synchronizations as $processCode => $definition) {
            if (!is_string($processCode)
                || trim($processCode) === ''
                || !is_array($definition)
                || !isset($definition['label'], $definition['description'])
                || !is_string($definition['label'])
                || trim($definition['label']) === ''
                || !is_string($definition['description'])
                || trim($definition['description']) === ''
                || (isset($definition['sort_order']) && !is_numeric($definition['sort_order']))
                || (isset($definition['monitor_only']) && !is_bool($definition['monitor_only']))
            ) {
                throw new InvalidArgumentException(
                    'Synchronization definitions must use the documented array contract.'
                );
            }

            $state = $this->cursorStorage->get($processCode);
            $items[] = [
                'process_code' => $processCode,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'cursor' => $state['cursor'] ?? null,
                'synced_at' => $state['synced_at'] ?? null,
                'sort_order' => (int)($definition['sort_order'] ?? 0),
                ...(!empty($definition['monitor_only']) ? ['monitor_only' => true] : []),
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int =>
                [$left['sort_order'], $left['label']] <=> [$right['sort_order'], $right['label']]
        );

        return array_map(
            static function (array $item): array {
                unset($item['sort_order']);

                return $item;
            },
            $items
        );
    }
}
