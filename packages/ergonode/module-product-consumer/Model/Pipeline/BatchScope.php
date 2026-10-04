<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Pipeline;

use LogicException;

/** Shared in one execution; never carries deferred work into another import. */
class BatchScope
{
    private ?BatchContext $context = null;

    public function get(): ?BatchContext
    {
        return $this->context;
    }

    public function run(BatchContext $context, callable $operation): void
    {
        if ($this->context !== null) {
            throw new LogicException('Nested Ergonode product batches are not supported.');
        }
        $this->context = $context;
        try {
            $operation();
        } finally {
            try {
                foreach ($context->data['cleanup'] ?? [] as $cleanup) {
                    $cleanup();
                }
            } finally {
                $this->context = null;
            }
        }
    }

    public function afterSuccess(\Closure $operation): bool
    {
        if ($this->context?->current === null) { return false; }
        $this->context->current->completion[] = $operation;
        return true;
    }

    public function recordProduct(int $productId): void
    {
        if ($this->context?->current !== null) {
            $this->context->current->productId = $productId;
        }
    }

    public function contains(int $productId): bool
    {
        return $this->context !== null && in_array($productId, $this->context->productIds(), true);
    }
}
