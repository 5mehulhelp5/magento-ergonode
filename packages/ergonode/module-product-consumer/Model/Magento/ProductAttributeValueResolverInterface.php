<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

interface ProductAttributeValueResolverInterface
{
    /** @param array<string, mixed> $mapping */
    public function supports(array $mapping): bool;

    /**
     * @param float|int|string|string[]|null $value
     * @param array<string, mixed> $mapping
     * @return float|int|string|string[]|null
     */
    public function resolve(
        float|int|string|array|null $value,
        array $mapping,
        string $languageCode,
        int $storeId
    ): float|int|string|array|null;
}
