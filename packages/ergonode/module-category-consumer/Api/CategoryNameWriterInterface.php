<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryNameWriterInterface
{
    /**
     * Write store values, removing an override when its value is null.
     *
     * @param int $categoryId
     * @param string $attributeCode
     * @param array<int, string|null> $values
     * @return int Number of changed values.
     */
    public function write(int $categoryId, string $attributeCode, array $values): int;
}
