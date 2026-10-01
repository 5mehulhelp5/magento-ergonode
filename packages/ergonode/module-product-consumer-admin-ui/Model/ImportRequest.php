<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumerAdminUi\Model;

use Ergonode\ProductAdminUi\Api\ProductSelectionInterface;
use Ergonode\ProductConsumer\Api\ProductImportBatchInterface;

class ImportRequest
{
    public function __construct(
        private readonly ProductSelectionInterface $selection,
        private readonly ProductImportBatchInterface $batch
    ) {
    }

    /** @return array<int, array{product_id: int, code: string, status: string, message: string}> */
    public function import(string $payload): array
    {
        return $this->batch->import($this->selection->decodeIds($payload));
    }
}
