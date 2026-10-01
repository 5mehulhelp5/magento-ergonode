<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataSourceInterface;
use Ergonode\ProductAttributeAdminUi\Api\VerifiedErgonodeMetadataRecorderInterface;

class ErgonodeMetadataCatalog implements ErgonodeMetadataProviderInterface, VerifiedErgonodeMetadataRecorderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $verifiedAttributes = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $verifiedOptions = [];

    /** @param ErgonodeMetadataSourceInterface[] $sources */
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly array $sources = []
    ) {
    }

    public function getAttributes(): array
    {
        return array_values($this->getAttributeMap());
    }

    public function getAttributeMap(): array
    {
        $attributes = [];
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            $code = trim((string)($row['ergonode_attribute_code'] ?? ''));
            if ($code !== '') {
                $attributes[$code] = [
                    'label' => $code, 'code' => $code,
                    'type' => (string)($row['ergonode_type'] ?? ''),
                    'scope' => 'unknown', 'active' => false,
                ];
            }
        }

        return array_replace($attributes, $this->getVerifiedAttributeMap());
    }

    public function getAttribute(string $code): ?array
    {
        return $this->getAttributeMap()[$code] ?? null;
    }

    public function getVerifiedAttributeMap(): array
    {
        $attributes = [];
        foreach ($this->sources as $source) {
            foreach ($source->getAttributes() as $attribute) {
                $code = trim((string)($attribute['code'] ?? ''));
                if ($code !== '') {
                    $attributes[$code] = $attribute;
                }
            }
        }

        return array_replace($attributes, $this->verifiedAttributes);
    }

    public function getOptions(string $attributeCode): array
    {
        $options = [];
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            if ((string)($row['ergonode_attribute_code'] ?? '') !== $attributeCode) {
                continue;
            }
            foreach ($this->mappingReader->getOptionRows((int)$row['mapping_id']) as $option) {
                $code = trim((string)($option['ergonode_option_code'] ?? ''));
                if ($code !== '') {
                    $options[$code] = [
                        'label' => $code, 'code' => $code, 'type' => 'option',
                        'scope' => 'unknown', 'active' => false,
                    ];
                }
            }
        }

        return array_values(array_replace($options, $this->getVerifiedOptions($attributeCode)));
    }

    public function getVerifiedOptions(string $attributeCode): array
    {
        $options = [];
        foreach ($this->sources as $source) {
            foreach ($source->getOptions($attributeCode) as $option) {
                $code = trim((string)($option['code'] ?? ''));
                if ($code !== '') {
                    $options[$code] = array_replace($options[$code] ?? [], $option);
                }
            }
        }

        foreach ($this->verifiedOptions[$attributeCode] ?? [] as $code => $option) {
            $options[$code] = array_replace($options[$code] ?? [], $option);
        }

        return $options;
    }

    public function getOptionCounts(array $attributeCodes): array
    {
        $codes = array_fill_keys($attributeCodes, true);
        $ids = [];
        foreach ($this->mappingReader->getAttributeRows() as $row) {
            $code = (string)($row['ergonode_attribute_code'] ?? '');
            if (isset($codes[$code])) {
                $ids[(int)$row['mapping_id']] = $code;
            }
        }
        $counts = [];
        foreach ($this->mappingReader->getCompleteOptionCounts(array_keys($ids)) as $id => $count) {
            $counts[$ids[$id]] = $count;
        }
        foreach ($this->sources as $source) {
            $counts = array_replace($counts, $source->getOptionCounts($attributeCodes));
        }
        foreach (array_keys($this->verifiedOptions) as $code) {
            if (isset($codes[$code])) {
                $counts[$code] = max($counts[$code] ?? 0, count($this->getOptions($code)));
            }
        }

        return $counts;
    }

    public function recordAttribute(array $attribute): void
    {
        $code = trim((string)($attribute['code'] ?? ''));
        if ($code !== '') {
            $this->verifiedAttributes[$code] = $attribute;
        }
    }

    public function recordOptions(string $attributeCode, array $options): void
    {
        foreach ($options as $option) {
            $code = trim((string)($option['code'] ?? ''));
            if ($code !== '') {
                $this->verifiedOptions[$attributeCode][$code] = $option;
            }
        }
    }
}
