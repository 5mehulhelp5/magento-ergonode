<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface MutationVariableInterface
{
    /**
     * @return string
     */
    public function getType(): string;

    /**
     * Return a deeply immutable JSON-compatible value composed only of
     * scalars, null and nested arrays.
     *
     * @return mixed
     */
    public function getValue(): mixed;
}
