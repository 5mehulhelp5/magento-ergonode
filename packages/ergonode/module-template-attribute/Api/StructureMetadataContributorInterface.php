<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttribute\Api;

interface StructureMetadataContributorInterface
{
    /**
     * @param string $templateCode
     * @param int $attributeSetId
     * @param array<string, mixed> $structure
     * @return array<string, mixed>
     */
    public function contribute(string $templateCode, int $attributeSetId, array $structure): array;
}
