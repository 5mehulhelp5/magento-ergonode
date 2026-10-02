<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Port;

interface RoleWriterInterface
{
    /** @param array<string,?string> $roles */
    public function write(int $productId, int $storeId, array $roles): void;
}
