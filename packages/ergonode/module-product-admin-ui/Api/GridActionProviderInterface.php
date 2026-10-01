<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Api;

interface GridActionProviderInterface
{
    /** @return array<string, mixed>|null Null when the administrator cannot use this action. */
    public function getConfiguration(): ?array;
}
