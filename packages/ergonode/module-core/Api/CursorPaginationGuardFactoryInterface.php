<?php

declare(strict_types=1);

namespace Ergonode\Core\Api;

interface CursorPaginationGuardFactoryInterface
{
    /**
     * @param string $context
     * @return CursorPaginationGuardInterface
     */
    public function create(string $context): CursorPaginationGuardInterface;
}
