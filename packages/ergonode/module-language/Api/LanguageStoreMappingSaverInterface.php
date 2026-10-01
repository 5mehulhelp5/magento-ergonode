<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

interface LanguageStoreMappingSaverInterface
{
    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @param string $expectedRevision Revision of the complete state presented to the editor.
     * @return array{stats: array{inserted: int, updated: int, deleted: int, unchanged: int}, revision: string}
     */
    public function save(array $mappings, array $visibility, string $expectedRevision): array;
}
