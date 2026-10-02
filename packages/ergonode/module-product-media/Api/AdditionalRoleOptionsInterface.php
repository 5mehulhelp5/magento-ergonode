<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface AdditionalRoleOptionsInterface
{
    /** @return array<string,string> Non-native image roles without ordinary attribute mappings. */
    public function getOptions(): array;
}
