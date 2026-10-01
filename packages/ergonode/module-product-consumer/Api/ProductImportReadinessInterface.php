<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

interface ProductImportReadinessInterface
{
    /** @return array{ready: bool, message: string} */
    public function getStatus(): array;
}
