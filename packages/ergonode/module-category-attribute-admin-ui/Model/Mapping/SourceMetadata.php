<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Model\Mapping;

use Ergonode\CategoryAttributeAdminUi\Api\SourceMetadataProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;

class SourceMetadata implements SourceMetadataProviderInterface
{
    /** @param SourceMetadataProviderInterface[] $providers */
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly array $providers = []
    ) {
    }

    public function getAttributes(): array
    {
        $attributes = [];
        foreach ($this->providers === [] ? $this->mappingReader->getAttributeRows() : [] as $row) {
            $code = (string)($row['ergonode_attribute_code'] ?? '');
            if ($code !== '') {
                $attributes[$code] = [
                    'code' => $code, 'label' => $code, 'type' => (string)($row['ergonode_type'] ?? ''),
                    'scope' => 'saved mapping', 'active' => true,
                ];
            }
        }
        foreach ($this->providers as $provider) {
            foreach ($provider->getAttributes() as $attribute) {
                $attributes[(string)$attribute['code']] = $attribute;
            }
        }
        return array_values($attributes);
    }

    public function getOptions(string $attributeCode): array
    {
        $options = [];
        if ($this->providers === []) {
            foreach ($this->mappingReader->getAttributeRows() as $attribute) {
                if (($attribute['ergonode_attribute_code'] ?? '') !== $attributeCode) {
                    continue;
                }
                foreach ($this->mappingReader->getOptionRows((int)$attribute['mapping_id']) as $row) {
                    $code = (string)($row['ergonode_option_code'] ?? '');
                    if ($code !== '') {
                        $options[$code] = ['code' => $code, 'label' => $code, 'type' => 'option', 'active' => true];
                    }
                }
            }
        }
        foreach ($this->providers as $provider) {
            foreach ($provider->getOptions($attributeCode) as $option) {
                $options[(string)$option['code']] = $option;
            }
        }
        return array_values($options);
    }
}
