<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

final readonly class AnalysisReport
{
    /** @param list<DuplicateGroup> $groups */
    public function __construct(
        public int $fileCount,
        public int $minimumScore,
        public array $groups
    ) {
    }

    public function hasDuplicates(): bool
    {
        return $this->groups !== [];
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     status: string,
     *     files: int,
     *     minimumScore: int,
     *     duplicateGroups: int,
     *     duplicates: list<array{
     *         score: int,
     *         statementCount: int,
     *         occurrenceCount: int,
     *         occurrences: list<array{file: string, startLine: int, endLine: int}>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'status' => $this->hasDuplicates() ? 'found' : 'ok',
            'files' => $this->fileCount,
            'minimumScore' => $this->minimumScore,
            'duplicateGroups' => count($this->groups),
            'duplicates' => array_map(
                static fn (DuplicateGroup $group): array => $group->toArray(),
                $this->groups
            ),
        ];
    }
}
