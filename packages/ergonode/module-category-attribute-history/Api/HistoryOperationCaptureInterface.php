<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Api;

interface HistoryOperationCaptureInterface
{
    /**
     * Record one operation, grouping nested captures without changing its result or exception.
     * History persistence is best-effort and does not control the wrapped operation.
     *
     * @template T
     * @param string $code
     * @param callable(): T $operation
     * @return T
     */
    public function execute(string $code, callable $operation): mixed;
}
