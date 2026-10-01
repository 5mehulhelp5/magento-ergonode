<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Api;

interface StructureProviderInterface
{
    /**
     * @param string $templateCode
     * @param int $attributeSetId
     * @return array<string, mixed>
     */
    public function get(string $templateCode, int $attributeSetId): array;
}
