<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\E2e\Support;

use InvalidArgumentException;

class CohortPlanner
{
    public const string BOTH = 'published_mapped';
    public const string PUBLISHED_ONLY = 'published_unmapped';
    public const string MAPPED_ONLY = 'unpublished_mapped';
    public const string NEITHER = 'unpublished_unmapped';

    /**
     * @param string[] $identifiers
     * @return array<string, string>
     */
    public function plan(array $identifiers, string $seed, int $publishedPercent = 70, int $mappedPercent = 70): array
    {
        $identifiers = $this->identifiers($identifiers);
        $this->validatePercent($publishedPercent);
        $this->validatePercent($mappedPercent);
        if ($identifiers === []) {
            return [];
        }

        $identifiers = $this->rank($identifiers, $seed);

        $count = count($identifiers);
        $published = (int)round($count * $publishedPercent / 100);
        $mapped = (int)round($count * $mappedPercent / 100);
        $both = (int)round($count * $publishedPercent * $mappedPercent / 10000);
        $both = max(0, min($both, $published, $mapped));
        $both = max($both, $published + $mapped - $count);
        $quotas = [
            self::BOTH => $both,
            self::PUBLISHED_ONLY => $published - $both,
            self::MAPPED_ONLY => $mapped - $both,
        ];
        $quotas[self::NEITHER] = $count - array_sum($quotas);

        $result = [];
        $offset = 0;
        foreach ($quotas as $cohort => $quota) {
            foreach (array_slice($identifiers, $offset, $quota) as $identifier) {
                $result[$identifier] = $cohort;
            }
            $offset += $quota;
        }
        ksort($result);

        return $result;
    }

    /**
     * Three roots deliberately cover the three most useful failure states.
     *
     * @param string[] $identifiers
     * @return array<string, string>
     */
    public function planRoots(array $identifiers, string $seed): array
    {
        $identifiers = $this->identifiers($identifiers);
        if (count($identifiers) < 3) {
            throw new InvalidArgumentException('The Ergonode E2E scenario requires at least three Magento roots.');
        }
        $ranked = $this->rank($identifiers, $seed);

        return [
            $ranked[0] => self::BOTH,
            $ranked[1] => self::PUBLISHED_ONLY,
            $ranked[2] => self::MAPPED_ONLY,
        ];
    }

    /**
     * @param array<string, string[]> $identifiersByGroup
     * @return array<string, bool>
     */
    public function selectByGroup(array $identifiersByGroup, string $seed, int $selectedPercent = 70): array
    {
        $this->validatePercent($selectedPercent);
        $groups = [];
        $total = 0;
        foreach ($identifiersByGroup as $group => $identifiers) {
            $identifiers = $this->identifiers($identifiers);
            if ($identifiers === []) {
                continue;
            }
            $groups[$group] = $this->rank($identifiers, $seed . '|' . $group);
            $total += count($identifiers);
        }
        $target = (int)round($total * $selectedPercent / 100);
        $quotas = [];
        $remainders = [];
        foreach ($groups as $group => $identifiers) {
            $exact = count($identifiers) * $selectedPercent / 100;
            $quotas[$group] = min(count($identifiers), (int)floor($exact));
            $remainders[$group] = $exact - floor($exact);
        }
        if ($target >= count($groups)) {
            foreach ($groups as $group => $identifiers) {
                $quotas[$group] = max(1, $quotas[$group]);
            }
        }
        while (array_sum($quotas) < $target) {
            $group = $this->nextQuotaGroup($groups, $quotas, $remainders, true);
            if ($group === null) {
                break;
            }
            $quotas[$group]++;
            $remainders[$group] = 0.0;
        }
        while (array_sum($quotas) > $target) {
            $group = $this->nextQuotaGroup($groups, $quotas, $remainders, false);
            if ($group === null) {
                break;
            }
            $quotas[$group]--;
        }

        $selection = [];
        foreach ($groups as $group => $ranked) {
            foreach ($ranked as $index => $identifier) {
                $selection[$identifier] = $index < $quotas[$group];
            }
        }
        ksort($selection);

        return $selection;
    }

    /** @param string[] $identifiers @return string[] */
    private function identifiers(array $identifiers): array
    {
        $result = [];
        foreach ($identifiers as $identifier) {
            $identifier = trim((string)$identifier);
            if ($identifier !== '') {
                $result[$identifier] = true;
            }
        }

        return array_keys($result);
    }

    private function validatePercent(int $percent): void
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Cohort percentages must be between 0 and 100.');
        }
    }

    /** @param string[] $identifiers @return string[] */
    private function rank(array $identifiers, string $seed): array
    {
        usort($identifiers, static function (string $left, string $right) use ($seed): int {
            $comparison = strcmp(
                hash('sha256', $seed . '|' . $left),
                hash('sha256', $seed . '|' . $right)
            );

            return $comparison !== 0 ? $comparison : strcmp($left, $right);
        });

        return $identifiers;
    }

    /**
     * @param array<string, string[]> $groups
     * @param array<string, int> $quotas
     * @param array<string, float> $remainders
     */
    private function nextQuotaGroup(array $groups, array $quotas, array $remainders, bool $adding): ?string
    {
        $candidates = [];
        foreach ($groups as $group => $identifiers) {
            if ($adding && $quotas[$group] >= count($identifiers)) {
                continue;
            }
            if (!$adding && $quotas[$group] <= 1) {
                continue;
            }
            $candidates[] = (string)$group;
        }
        usort($candidates, static function (string $left, string $right) use ($remainders, $adding): int {
            $comparison = $remainders[$left] <=> $remainders[$right];
            if ($adding) {
                $comparison *= -1;
            }

            return $comparison !== 0 ? $comparison : strcmp($left, $right);
        });

        return $candidates[0] ?? null;
    }
}
