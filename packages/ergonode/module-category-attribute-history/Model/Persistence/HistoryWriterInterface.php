<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model\Persistence;

interface HistoryWriterInterface
{
    /** @param array<string, mixed> $operation */
    public function save(array $operation): void;
}
