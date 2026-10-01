<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface TemplateMappingSaverInterface
{
    /**
     * @param array<string, int|string|null> $mappings
     * @return array{assigned: int, unassigned: int, unchanged: int}
     */
    public function save(array $mappings): array;
}
