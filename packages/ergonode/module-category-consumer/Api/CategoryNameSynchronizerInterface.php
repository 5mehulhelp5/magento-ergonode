<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryNameSynchronizerInterface
{
    /**
     * @param int $categoryId
     * @param array<string, string> $labels
     * @return int Number of changed store values.
     */
    public function synchronize(int $categoryId, array $labels): int;
}
