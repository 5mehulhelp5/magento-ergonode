<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Api;

interface ErgonodeMetadataProviderInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getAttributes(): array;

    /** @return array<string, array<string, mixed>> */
    public function getAttributeMap(): array;

    /**
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function getAttribute(string $code): ?array;

    /** @return array<string, array<string, mixed>> */
    public function getVerifiedAttributeMap(): array;

    /**
     * @param string $attributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(string $attributeCode): array;

    /**
     * @param string $attributeCode
     * @return array<string, array<string, mixed>>
     */
    public function getVerifiedOptions(string $attributeCode): array;

    /**
     * @param string[] $attributeCodes
     * @return array<string, int>
     */
    public function getOptionCounts(array $attributeCodes): array;
}
