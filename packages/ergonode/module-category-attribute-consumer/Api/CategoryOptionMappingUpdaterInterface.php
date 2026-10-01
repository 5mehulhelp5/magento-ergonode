<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface CategoryOptionMappingUpdaterInterface
{
    /**
     * @param int $attributeMappingId
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array;
}
