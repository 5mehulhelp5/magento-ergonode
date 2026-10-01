<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model\Operation;

class OperationContext
{
    private int $depth = 0;
    private bool $grouped = false;
    private ?int $operationId = null;

    public function beginGroup(?int $operationId): void
    {
        $this->grouped = true;
        $this->operationId = $operationId;
    }

    public function endGroup(): void
    {
        $this->grouped = false;
        $this->operationId = null;
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }

    public function getOperationId(): ?int
    {
        return $this->operationId;
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function enter(): void
    {
        $this->depth++;
    }

    public function leave(): void
    {
        $this->depth = max(0, $this->depth - 1);
    }
}
