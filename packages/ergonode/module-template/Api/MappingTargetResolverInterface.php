<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface MappingTargetResolverInterface
{
    /**
     * Resolve an extension-owned target marker, or return null for an existing set ID.
     *
     * @param string $templateCode
     * @param int|string $target
     * @return int|null
     */
    public function resolve(string $templateCode, int|string $target): ?int;
}
