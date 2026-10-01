<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface MappingReaderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttributeRows(): array;

    /**
     * @param  int $mappingId
     * @return array<string, mixed>|null
     */
    public function getAttributeRow(int $mappingId): ?array;

    /**
     * @param  int $attributeMappingId
     * @return array<int, array<string, mixed>>
     */
    public function getOptionRows(int $attributeMappingId): array;

    /**
     * @param  int[] $attributeMappingIds
     * @return array<int, int>
     */
    public function getCompleteOptionCounts(array $attributeMappingIds): array;
}
