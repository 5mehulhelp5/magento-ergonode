<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface AttributeMappingWriterInterface
{
    /**
     * Validate resolved metadata before the caller creates pending Magento definitions.
     *
     * @param array<int, array<string, mixed>> $mappings
     * @return void
     */
    public function validate(array $mappings): void;

    /**
     * Save mappings whose metadata has been resolved by the caller. Does not create attributes or write values.
     *
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(array $mappings, array $visibility): array;
}
