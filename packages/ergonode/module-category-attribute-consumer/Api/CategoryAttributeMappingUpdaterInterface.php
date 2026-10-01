<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface CategoryAttributeMappingUpdaterInterface
{
    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array<string, mixed>
     */
    public function save(array $mappings, array $visibility): array;
}
