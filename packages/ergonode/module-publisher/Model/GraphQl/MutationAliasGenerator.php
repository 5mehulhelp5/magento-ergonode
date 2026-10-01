<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

class MutationAliasGenerator
{
    /**
     * @param array<string, true> $usedAliases
     */
    public function generate(string $candidate, array &$usedAliases): string
    {
        $alias = $candidate;
        $suffix = 2;

        while (isset($usedAliases[$alias])) {
            $alias = $candidate . '_' . $suffix;
            ++$suffix;
        }

        $usedAliases[$alias] = true;

        return $alias;
    }
}
