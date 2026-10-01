<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Data;

final readonly class ProductImportWorkItem
{
    public const string OPERATION_SYNCHRONIZE = 'sync';
    public const string OPERATION_DELETE = 'delete';

    public function __construct(
        public int $itemId,
        public string $sku,
        public string $operation,
        public ?array $payload,
        public string $eventToken,
        public string $leaseToken,
        public int $attemptCount
    ) {
    }
}
