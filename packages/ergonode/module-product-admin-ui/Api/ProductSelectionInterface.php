<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Api;

interface ProductSelectionInterface
{
    /**
     * @param string $payload
     * @return int[]
     */
    public function snapshot(string $payload): array;

    /**
     * @param string $payload
     * @return int[]
     */
    public function decodeIds(string $payload): array;
}
