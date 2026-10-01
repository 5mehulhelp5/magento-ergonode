<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Api;

interface MappingPolicyInterface
{
    /**
     * @param string $attributeCode
     * @return bool
     */
    public function isMappable(string $attributeCode): bool;

    /**
     * @param string $attributeCode
     * @param bool $nativeRequired
     * @return bool
     */
    public function isRequiredMapping(string $attributeCode, bool $nativeRequired): bool;
}
