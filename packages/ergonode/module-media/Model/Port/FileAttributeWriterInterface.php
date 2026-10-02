<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Port;

interface FileAttributeWriterInterface
{
    public function write(int $productId, string $code, int $storeId, string $path): void;
    public function clear(int $productId, string $code, int $storeId): void;
}
