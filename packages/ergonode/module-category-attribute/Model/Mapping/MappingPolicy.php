<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Mapping;

use Ergonode\CategoryAttribute\Api\MappingPolicyInterface;

class MappingPolicy implements MappingPolicyInterface
{
    /** @param string[] $excludedAttributeCodes */
    public function __construct(private readonly array $excludedAttributeCodes = [])
    {
    }

    public function isMappable(string $attributeCode): bool
    {
        $attributeCode = trim($attributeCode);

        return $attributeCode !== '' && !in_array($attributeCode, $this->excludedAttributeCodes, true);
    }

    public function isRequiredMapping(string $attributeCode, bool $nativeRequired): bool
    {
        return $this->isMappable($attributeCode) && $nativeRequired;
    }
}
