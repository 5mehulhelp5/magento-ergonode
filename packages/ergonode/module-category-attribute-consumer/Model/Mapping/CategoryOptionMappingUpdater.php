<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Api\CategoryOptionMappingUpdaterInterface;
use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;

class CategoryOptionMappingUpdater implements CategoryOptionMappingUpdaterInterface
{
    public function __construct(
        private readonly CategoryOptionMappingSaver $mappingSaver,
        private readonly MappingSynchronizationInterface $synchronization
    ) {
    }

    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        return $this->synchronization->execute(
            function () use ($attributeMappingId, $mappings, $visibility): array {
                $stats = $this->mappingSaver->save($attributeMappingId, $mappings, $visibility);

                return $stats;
            }
        );
    }
}
