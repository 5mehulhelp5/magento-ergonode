<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface CategoryAttributeManagementProviderInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getErgonodeAttributes(): array;

    /** @return array<int, array<string, mixed>> */
    public function getMagentoAttributes(): array;

    /** @return array<int, array<string, mixed>> */
    public function getAttributeMappings(): array;

    /** @return array<int, array{mapped: int, total: int}> */
    public function getOptionMappingProgress(): array;

    /**
     * @param int $mappingId
     * @return array<string, mixed>
     */
    public function getAttributeMappingRow(int $mappingId): array;

    /**
     * @param int|null $mappingId
     * @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>}
     */
    public function getOptionContext(?int $mappingId): array;

    /**
     * @param int $mappingId
     * @param string $ergonodeAttributeCode
     * @param string $magentoAttributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getOptionMappings(
        int $mappingId,
        string $ergonodeAttributeCode,
        string $magentoAttributeCode
    ): array;

    /**
     * @param string $attributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getErgonodeOptions(string $attributeCode): array;

    /**
     * @param string $attributeCode
     * @return array<int, array<string, mixed>>
     */
    public function getMagentoOptions(string $attributeCode): array;

    /**
     * @param string $attributeCode
     * @return void
     */
    public function refreshOptions(string $attributeCode): void;

    /**
     * @param string $attributeCode
     * @param string $optionCode
     * @return void
     */
    public function removeOptionSnapshot(string $attributeCode, string $optionCode): void;
}
