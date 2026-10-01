<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\CursorPaginationGuardInterface;

class CursorPaginationGuardFactory implements CursorPaginationGuardFactoryInterface
{
    public function create(string $context): CursorPaginationGuardInterface
    {
        return new CursorPaginationGuard($context);
    }
}
