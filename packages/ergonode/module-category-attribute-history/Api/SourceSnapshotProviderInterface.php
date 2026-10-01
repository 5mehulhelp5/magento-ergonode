<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Api;

interface SourceSnapshotProviderInterface
{
    /**
     * Return fresh source metadata without downloading or modifying it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAttributeMap(): array;
}
