<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface MagentoOptionProviderInterface
{
    /**
     * @param string $attributeCode
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getOptions(string $attributeCode): array;
}
