<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Provider;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingProvider;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryOptionMappingProvider;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;

class CategoryAttributeManagementProvider implements CategoryAttributeManagementProviderInterface
{
    public function __construct(
        private readonly ErgonodeCategoryAttributeProvider $ergonodeAttributeProvider,
        private readonly MagentoAttributeProviderInterface $magentoAttributeProvider,
        private readonly CategoryAttributeMappingProvider $attributeMappingProvider,
        private readonly CategoryOptionMappingProvider $optionMappingProvider,
        private readonly ErgonodeOptionProviderInterface $ergonodeOptionProvider,
        private readonly MagentoOptionProviderInterface $magentoOptionProvider,
        private readonly AttributeCacheRefresherInterface $cacheRefresher,
        private readonly OptionSnapshotRemoverInterface $snapshotRemover
    ) {
    }

    public function getErgonodeAttributes(): array
    {
        return $this->ergonodeAttributeProvider->getAttributes();
    }

    public function getMagentoAttributes(): array
    {
        return $this->magentoAttributeProvider->getAttributes();
    }

    public function getAttributeMappings(): array
    {
        return $this->attributeMappingProvider->getMappings();
    }

    public function getOptionMappingProgress(): array
    {
        return $this->attributeMappingProvider->getOptionMappingProgress();
    }

    public function getAttributeMappingRow(int $mappingId): array
    {
        return $this->attributeMappingProvider->getMappingRow($mappingId) ?? [];
    }

    public function getOptionContext(?int $mappingId): array
    {
        return $this->optionMappingProvider->getContext($mappingId);
    }

    public function getOptionMappings(
        int $mappingId,
        string $ergonodeAttributeCode,
        string $magentoAttributeCode
    ): array {
        return $this->optionMappingProvider->getMappings(
            $mappingId,
            $ergonodeAttributeCode,
            $magentoAttributeCode
        );
    }

    public function getErgonodeOptions(string $attributeCode): array
    {
        return $this->ergonodeOptionProvider->getOptions($attributeCode);
    }

    public function getMagentoOptions(string $attributeCode): array
    {
        return $this->magentoOptionProvider->getOptions($attributeCode);
    }

    public function refreshOptions(string $attributeCode): void
    {
        $this->cacheRefresher->refreshOptions($attributeCode);
    }

    public function removeOptionSnapshot(string $attributeCode, string $optionCode): void
    {
        $this->snapshotRemover->remove($attributeCode, $optionCode);
    }
}
