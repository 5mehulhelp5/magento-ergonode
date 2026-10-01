<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface OptionMappingWriterInterface
{
    /**
     * Save resolved option identifiers without creating Magento options.
     *
     * @param int $attributeMappingId
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array;
}
