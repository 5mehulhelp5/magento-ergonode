<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSuggester;

use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterface;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\Exception\LocalizedException;

class AttributeAutoMapper implements AttributeAutoMapperInterface
{
    public function __construct(
        private readonly ErgonodeAttributeProviderInterface $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly AttributeMappingSuggester $mappingSuggester,
        private readonly AttributeMappingSaver $attributeMappingSaver,
        private readonly IdenticalCodeAttributeCreator $attributeCreator,
        private readonly ProductAttributeConfigProvider $attributeConfigProvider
    ) {
    }

    public function suggest(array $mappings, array $visibility = []): array
    {
        return $this->mappingSuggester->suggest(
            $this->ergonodeAttributeProvider->getAttributeMap(),
            $this->magentoAttributeProvider->getAttributeMap(),
            $mappings,
            $visibility
        );
    }

    public function synchronize(): array
    {
        if (!$this->attributeConfigProvider->shouldMapIdenticalCodes()) {
            return [
                'matched' => 0,
                'conflicts' => 0,
                'created' => 0,
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
                'unchanged' => 0,
            ];
        }

        $mappings = $this->attributeMappingProvider->getMappings();
        $suggestion = $this->suggest($mappings);
        $creation = $this->attributeCreator->create($mappings);
        $matches = array_merge($suggestion['matches'], $creation['matches']);
        $stats = $this->attributeMappingSaver->saveAdditions($matches);
        $this->attributeMappingProvider->clearCache();

        return [
            'matched' => count($matches),
            'conflicts' => count($suggestion['conflicts']) + count($creation['conflicts']),
            'created' => $creation['created'],
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'deleted' => $stats['deleted'],
            'unchanged' => $stats['unchanged'],
        ];
    }
}
