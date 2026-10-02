<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

final class DuplicateGroup
{
    /** @var array<string, DuplicateOccurrence> */
    private array $occurrences = [];

    public function __construct(
        public readonly int $score,
        public readonly int $statementCount
    ) {
    }

    public function add(DuplicateOccurrence $occurrence): void
    {
        $this->occurrences[$occurrence->key()] = $occurrence;
    }

    /** @return list<DuplicateOccurrence> */
    public function occurrences(): array
    {
        $occurrences = array_values($this->occurrences);
        usort(
            $occurrences,
            static fn (DuplicateOccurrence $left, DuplicateOccurrence $right): int =>
                [$left->file, $left->startLine, $left->endLine]
                <=> [$right->file, $right->startLine, $right->endLine]
        );

        return $occurrences;
    }

    public function occurrenceCount(): int
    {
        return count($this->occurrences);
    }

    public function isCoveredBy(self $other): bool
    {
        foreach ($this->occurrences() as $occurrence) {
            $covered = false;
            foreach ($other->occurrences() as $otherOccurrence) {
                if ($occurrence->isContainedBy($otherOccurrence)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     score: int,
     *     statementCount: int,
     *     occurrenceCount: int,
     *     occurrences: list<array{file: string, startLine: int, endLine: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'statementCount' => $this->statementCount,
            'occurrenceCount' => $this->occurrenceCount(),
            'occurrences' => array_map(
                static fn (DuplicateOccurrence $occurrence): array => $occurrence->toArray(),
                $this->occurrences()
            ),
        ];
    }
}
