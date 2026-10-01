<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Api;

interface SourceMetadataProviderInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getAttributes(): array;

    /**
     * @param string $attributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(string $attributeCode): array;
}
