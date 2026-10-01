<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Port;

interface SelectedProductWriterInterface
{
    /**
     * @param array<string, array<int, float|int|string|string[]>> $values
     * @param array<string, int[]> $clear
     */
    public function write(int $productId, array $values, array $clear): void;
}
