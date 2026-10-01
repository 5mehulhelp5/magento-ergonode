<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Sync;

use Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\OptionSynchronizationParticipantInterface;
use InvalidArgumentException;

class OptionSynchronizationPool implements OptionSynchronizationInterface
{
    /** @var array<string, OptionSynchronizationParticipantInterface> */
    private array $participants;

    /** @param array<string, mixed> $participants */
    public function __construct(array $participants = [])
    {
        $this->participants = [];
        foreach ($participants as $name => $participant) {
            if (!$participant instanceof OptionSynchronizationParticipantInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Option synchronization participant "%s" must implement %s.',
                    $name,
                    OptionSynchronizationParticipantInterface::class
                ));
            }
            $this->participants[$name] = $participant;
        }
    }

    /**
     * @param string[] $attributeCodes
     * @return array{
     *     mappings: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function executeForAttributeCodes(array $attributeCodes): array
    {
        $attributeCodes = $this->normalizeAttributeCodes($attributeCodes);
        if ($attributeCodes === []) {
            return ['mappings' => [], 'summary' => $this->emptySummary()];
        }

        $mappings = [];
        $summary = $this->emptySummary();
        foreach ($this->participants as $participant) {
            $result = $participant->executeForAttributeCodes($attributeCodes);
            foreach ($result['mappings'] as $mapping) {
                $mappings[] = $mapping;
            }
            foreach ($result['summary'] as $name => $value) {
                $summary[$name] = ($summary[$name] ?? 0) + $value;
            }
        }

        return ['mappings' => $mappings, 'summary' => $summary];
    }

    /** @param string[] $attributeCodes @return string[] */
    private function normalizeAttributeCodes(array $attributeCodes): array
    {
        $normalized = [];
        foreach ($attributeCodes as $attributeCode) {
            $attributeCode = trim($attributeCode);
            if ($attributeCode !== '') {
                $normalized[$attributeCode] = $attributeCode;
            }
        }

        return array_values($normalized);
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'created' => 0,
            'linked' => 0,
            'mappings_inserted' => 0,
            'mappings_updated' => 0,
            'labels_updated' => 0,
            'sort_order_updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }
}
