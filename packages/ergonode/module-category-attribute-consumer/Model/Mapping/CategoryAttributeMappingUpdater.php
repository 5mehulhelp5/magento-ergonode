<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingUpdaterInterface;
use Ergonode\CategoryAttributeConsumer\Api\MappingSynchronizationInterface;

class CategoryAttributeMappingUpdater implements CategoryAttributeMappingUpdaterInterface
{
    public function __construct(
        private readonly CategoryAttributeMappingSaver $mappingSaver,
        private readonly MappingSynchronizationInterface $synchronization
    ) {
    }

    public function save(array $mappings, array $visibility): array
    {
        return $this->synchronization->execute(function () use ($mappings, $visibility): array {
            $stats = $this->mappingSaver->save($mappings, $visibility);

            return $stats;
        });
    }
}
