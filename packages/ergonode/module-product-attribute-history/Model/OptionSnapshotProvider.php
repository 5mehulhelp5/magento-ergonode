<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model;

use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProviderFactory;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;

class OptionSnapshotProvider
{
    public function __construct(
        private readonly ErgonodeOptionProviderFactory $sourceFactory,
        private readonly MagentoOptionsFactory $targetFactory,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilder $stateBuilder
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $sourceAttributes
     * @param array<string, array<string, mixed>> $targetAttributes
     * @param array<int, array<string, mixed>> $mappings
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    public function getState(array $sourceAttributes, array $targetAttributes, array $mappings): array
    {
        $sourceCache = new OptionSnapshotCache();
        $providers = [
            'source' => $this->sourceFactory->create(['snapshotCache' => $sourceCache]),
            'target' => $this->targetFactory->create(),
        ];
        $state = ['source' => [], 'target' => []];
        foreach (['source' => $sourceAttributes, 'target' => $targetAttributes] as $side => $attributes) {
            foreach ($attributes as $code => $attribute) {
                $state[$side][$code] = [];
                foreach ($providers[$side]->getOptions((string)$code) as $option) {
                    $state[$side][$code][$option['code']] = $this->normalize($option);
                }
            }
        }
        foreach ($this->stateBuilder->optionContexts($mappings) as $context) {
            $sourceCode = $context['left']['code'];
            $targetCode = $context['right']['code'];
            $options = $this->stateBuilder->options(
                $this->mappingReader->getOptionRows($context['mapping_id']),
                array_values($state['source'][$sourceCode] ?? []),
                array_values($state['target'][$targetCode] ?? [])
            );
            foreach ($options as $mapping) {
                $left = $mapping['left'];
                $right = $mapping['right'];
                if ($left !== null) {
                    $state['source'][$sourceCode][$left['code']] = $this->normalize($left, $right, $targetCode);
                }
                if ($right !== null) {
                    $state['target'][$targetCode][$right['code']] = $this->normalize($right, $left, $sourceCode);
                }
            }
        }
        foreach ($state as &$attributes) {
            ksort($attributes);
            foreach ($attributes as &$options) {
                ksort($options);
                $options = array_values($options);
            }
            unset($options);
        }
        unset($attributes);

        return $state;
    }

    /**
     * @param array<string, mixed> $option
     * @param array<string, mixed>|null $linked
     * @return array<string, mixed>
     */
    private function normalize(array $option, ?array $linked = null, ?string $linkedAttribute = null): array
    {
        return [
            'code' => (string)$option['code'], 'label' => (string)$option['label'],
            'type' => (string)$option['type'], 'scope' => (string)$option['scope'],
            'active' => (bool)($option['active'] ?? true),
            'mapped_code' => $linked['code'] ?? null,
            'mapped_attribute_code' => $linked === null ? null : $linkedAttribute,
        ];
    }
}
