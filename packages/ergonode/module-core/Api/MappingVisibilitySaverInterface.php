<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

/**
 * @phpstan-type MappingVisibilityItem array{
 *     entity_type: string,
 *     source: string,
 *     parent_identifier?: string,
 *     identifier: string,
 *     active: bool
 * }
 */
interface MappingVisibilitySaverInterface
{
    /**
     * @param MappingVisibilityItem[] $items
     * @return void
     */
    public function saveMany(array $items): void;

    /**
     * @param string $entityType
     * @param string $source
     * @param string $parentIdentifier
     * @return void
     */
    public function deleteByParentIdentifier(
        string $entityType,
        string $source,
        string $parentIdentifier
    ): void;
}
