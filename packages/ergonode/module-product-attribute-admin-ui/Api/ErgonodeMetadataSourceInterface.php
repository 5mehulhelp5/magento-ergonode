<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Api;

interface ErgonodeMetadataSourceInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getAttributes(): array;

    /**
     * @param string $attributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(string $attributeCode): array;

    /**
     * @param string[] $attributeCodes
     * @return array<string, int>
     */
    public function getOptionCounts(array $attributeCodes): array;
}
