<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;

final readonly class MutationBatch implements MutationBatchInterface
{
    /**
     * @param array<string, mixed> $variables
     * @param array<string, MutationOperationInterface> $operationsByAlias
     */
    public function __construct(
        private string $document,
        private array $variables,
        private array $operationsByAlias
    ) {
    }

    public function getDocument(): string
    {
        return $this->document;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @return array<string, MutationOperationInterface>
     */
    public function getOperationsByAlias(): array
    {
        return $this->operationsByAlias;
    }

    public function isEmpty(): bool
    {
        return $this->operationsByAlias === [];
    }
}
