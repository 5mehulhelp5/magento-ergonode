<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface TemplateSnapshotProviderInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getTemplates(): array;
}
