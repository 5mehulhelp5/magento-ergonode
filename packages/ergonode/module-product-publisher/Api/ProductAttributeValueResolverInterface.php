<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

interface ProductAttributeValueResolverInterface
{
    /**
     * @param array<string, mixed> $mapping
     * @return bool
     */
    public function supports(array $mapping): bool;

    /**
     * @param mixed $value
     * @param array<string, mixed> $mapping
     * @param string $context
     * @return float|string|string[]|null
     */
    public function resolve(mixed $value, array $mapping, string $context): float|string|array|null;
}
