<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

interface ProductAttributeMappingDeferrerInterface
{
    /**
     * Check whether an optional extension owns persistence for the mapping.
     *
     * @param array<string, mixed> $mapping
     * @return bool
     */
    public function supports(array $mapping): bool;
}
