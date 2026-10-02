<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use JsonException;
use PhpParser\Node\Stmt;

final readonly class DuplicateDetector
{
    public function __construct(
        private AstNormalizer $normalizer,
        private StatementMetric $metric
    ) {
    }

    /**
     * @param list<StatementSequence> $sequences
     * @return list<DuplicateGroup>
     * @throws JsonException
     */
    public function detect(array $sequences, int $minimumScore, int $minimumOccurrences = 2): array
    {
        if ($minimumScore < 1) {
            throw new \InvalidArgumentException('The minimum score must be greater than zero.');
        }
        if ($minimumOccurrences < 2) {
            throw new \InvalidArgumentException('At least two occurrences are required.');
        }

        $seedGroups = $this->buildSeedGroups($sequences, $minimumScore);
        /** @var array<string, array{canonical: string, group: DuplicateGroup}> $candidates */
        $candidates = [];

        foreach ($seedGroups as $seeds) {
            if (count($seeds) < $minimumOccurrences) {
                continue;
            }
            $seedCount = count($seeds);
            for ($leftIndex = 0; $leftIndex < $seedCount; $leftIndex++) {
                for ($rightIndex = $leftIndex + 1; $rightIndex < $seedCount; $rightIndex++) {
                    $left = $seeds[$leftIndex];
                    $right = $seeds[$rightIndex];
                    if ($left['coarse'] !== $right['coarse'] || $this->overlaps($left, $right)) {
                        continue;
                    }

                    $leftEnd = $left['end'];
                    $rightEnd = $right['end'];
                    $canonical = $this->normalizer->normalize(
                        array_slice($left['sequence']->statements, $left['start'], $leftEnd - $left['start'])
                    );
                    $rightCanonical = $this->normalizer->normalize(
                        array_slice($right['sequence']->statements, $right['start'], $rightEnd - $right['start'])
                    );
                    if ($canonical !== $rightCanonical) {
                        continue;
                    }

                    while (isset($left['sequence']->statements[$leftEnd], $right['sequence']->statements[$rightEnd])) {
                        $proposedLeft = $left;
                        $proposedLeft['end'] = $leftEnd + 1;
                        $proposedRight = $right;
                        $proposedRight['end'] = $rightEnd + 1;
                        if ($this->overlaps($proposedLeft, $proposedRight)) {
                            break;
                        }

                        $leftCanonical = $this->normalizer->normalize(array_slice(
                            $left['sequence']->statements,
                            $left['start'],
                            ($leftEnd + 1) - $left['start']
                        ));
                        $rightCanonical = $this->normalizer->normalize(array_slice(
                            $right['sequence']->statements,
                            $right['start'],
                            ($rightEnd + 1) - $right['start']
                        ));
                        if ($leftCanonical !== $rightCanonical) {
                            break;
                        }

                        $canonical = $leftCanonical;
                        $leftEnd++;
                        $rightEnd++;
                    }

                    $statementCount = $leftEnd - $left['start'];
                    $score = $this->rangeScore($left['sequence'], $left['start'], $leftEnd);
                    $key = hash('sha256', $canonical) . ':' . $statementCount;
                    if (!isset($candidates[$key])) {
                        $candidates[$key] = [
                            'canonical' => $canonical,
                            'group' => new DuplicateGroup($score, $statementCount),
                        ];
                    } elseif ($candidates[$key]['canonical'] !== $canonical) {
                        continue;
                    }

                    $candidates[$key]['group']->add($this->occurrence(
                        $left['sequence'],
                        $left['start'],
                        $leftEnd
                    ));
                    $candidates[$key]['group']->add($this->occurrence(
                        $right['sequence'],
                        $right['start'],
                        $rightEnd
                    ));
                }
            }
        }

        $groups = [];
        foreach ($candidates as $candidate) {
            if ($candidate['group']->occurrenceCount() >= $minimumOccurrences) {
                $groups[] = $candidate['group'];
            }
        }
        usort(
            $groups,
            static fn (DuplicateGroup $left, DuplicateGroup $right): int =>
                [$right->score, $right->statementCount, $right->occurrenceCount()]
                <=> [$left->score, $left->statementCount, $left->occurrenceCount()]
        );

        $maximalGroups = [];
        foreach ($groups as $group) {
            foreach ($maximalGroups as $maximalGroup) {
                if ($group->isCoveredBy($maximalGroup)) {
                    continue 2;
                }
            }
            $maximalGroups[] = $group;
        }

        return $maximalGroups;
    }

    /**
     * @param list<StatementSequence> $sequences
     * @return array<string, list<array{sequence: StatementSequence, start: int, end: int, coarse: string}>>
     * @throws JsonException
     */
    private function buildSeedGroups(array $sequences, int $minimumScore): array
    {
        $groups = [];
        foreach ($sequences as $sequence) {
            $count = count($sequence->statements);
            for ($start = 0; $start < $count; $start++) {
                $score = 0;
                $end = $start;
                while ($end < $count && $score < $minimumScore) {
                    $score += $this->metric->score($sequence->statements[$end]);
                    $end++;
                }
                if ($score < $minimumScore) {
                    continue;
                }

                $coarse = $this->normalizer->normalize(
                    array_slice($sequence->statements, $start, $end - $start),
                    true
                );
                $groups[hash('sha256', $coarse)][] = [
                    'sequence' => $sequence,
                    'start' => $start,
                    'end' => $end,
                    'coarse' => $coarse,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param array{sequence: StatementSequence, start: int, end: int} $left
     * @param array{sequence: StatementSequence, start: int, end: int} $right
     */
    private function overlaps(array $left, array $right): bool
    {
        return $left['sequence']->id === $right['sequence']->id
            && $left['start'] < $right['end']
            && $right['start'] < $left['end'];
    }

    private function rangeScore(StatementSequence $sequence, int $start, int $end): int
    {
        return array_sum(array_map(
            fn (Stmt $statement): int => $this->metric->score($statement),
            array_slice($sequence->statements, $start, $end - $start)
        ));
    }

    private function occurrence(StatementSequence $sequence, int $start, int $end): DuplicateOccurrence
    {
        return new DuplicateOccurrence(
            $sequence->id,
            $start,
            $end,
            $sequence->file,
            $sequence->statements[$start]->getStartLine(),
            $sequence->statements[$end - 1]->getEndLine()
        );
    }
}
