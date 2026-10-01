<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface OptionSynchronizationParticipantInterface
{
    /**
     * Synchronize options only for mappings owned by this participant.
     *
     * @param string[] $attributeCodes
     * @return array{
     *     mappings: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function executeForAttributeCodes(array $attributeCodes): array;
}
