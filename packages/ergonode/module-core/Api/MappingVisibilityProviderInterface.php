<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface MappingVisibilityProviderInterface
{
    /**
     * @param string $entityType
     * @param string $source
     * @param string[] $identifiers
     * @param string $parentIdentifier
     * @return array<string, bool>
     */
    public function getActiveMap(
        string $entityType,
        string $source,
        array $identifiers,
        string $parentIdentifier = ''
    ): array;
}
