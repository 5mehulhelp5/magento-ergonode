<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Api;

interface WorkspaceConfigProviderInterface
{
    /** @return array<string, mixed> */
    public function getConfig(): array;
}
