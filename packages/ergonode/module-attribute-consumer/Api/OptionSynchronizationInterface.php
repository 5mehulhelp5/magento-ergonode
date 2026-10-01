<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface OptionSynchronizationInterface
{
    /**
     * Synchronize the requested attribute codes through all registered participants.
     *
     * Codes are trimmed and deduplicated; an empty selection invokes no participant.
     * Results are aggregated, while each participant owns its target mappings.
     * Participant failures propagate and earlier successful writes may remain.
     * Register named OptionSynchronizationParticipantInterface objects in this
     * contract's global DI participants argument.
     *
     * @param string[] $attributeCodes
     * @return array{
     *     mappings: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function executeForAttributeCodes(array $attributeCodes): array;
}
