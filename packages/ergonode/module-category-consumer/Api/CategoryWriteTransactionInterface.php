<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryWriteTransactionInterface
{
    /**
     * Commit local category writes together, or roll them all back.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed;
}
