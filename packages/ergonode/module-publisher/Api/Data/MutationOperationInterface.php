<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface MutationOperationInterface
{
    /**
     * @return string
     */
    public function getField(): string;

    /**
     * @return string|null
     */
    public function getAlias(): ?string;

    /**
     * @return array<string, MutationVariableInterface>
     */
    public function getVariables(): array;

    /**
     * @return string[]
     */
    public function getResponseFields(): array;

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function getMetadata(): array;
}
