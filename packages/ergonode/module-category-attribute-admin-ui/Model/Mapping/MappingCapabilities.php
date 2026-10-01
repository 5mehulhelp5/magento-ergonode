<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

class MappingCapabilities
{
    /** @param array<string, array<string, mixed>> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    /** @return array<string, mixed> */
    public function getConfig(string $kind): array
    {
        return $this->config[$kind] ?? [];
    }
}
