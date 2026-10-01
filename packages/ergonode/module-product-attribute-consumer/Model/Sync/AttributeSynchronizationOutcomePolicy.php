<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

class AttributeSynchronizationOutcomePolicy
{
    /**
     * An incomplete batch never reaches this policy because infrastructure failures throw.
     * Business conflicts remain repairable through the explicit CLI and admin actions.
     *
     * @param array<string, int> $mappingStats
     * @param array<string, int> $optionStats
     * @return array{cursor_advance_allowed: bool, review_required: int}
     */
    public function completed(array $mappingStats, array $optionStats): array
    {
        return [
            'cursor_advance_allowed' => true,
            'review_required' => (int)($mappingStats['conflicts'] ?? 0)
                + (int)($optionStats['errors'] ?? 0)
                + (int)($optionStats['skipped'] ?? 0),
        ];
    }
}
