<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface MutationBatchInterface
{
    /**
     * @return string
     */
    public function getDocument(): string;

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array;

    /**
     * @return array<string, MutationOperationInterface>
     */
    public function getOperationsByAlias(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;
}
