<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api\Data;

interface SynchronizationResultInterface
{
    /**
     * @return MutationResultInterface[]
     */
    public function getResults(): array;

    /**
     * @return bool
     */
    public function isSuccessful(): bool;
}
