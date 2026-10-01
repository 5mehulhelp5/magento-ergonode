<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Model;

use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProviderFactory;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Ergonode\ProductAttribute\Model\Mapping\MappingStateBuilder;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProviderFactory;

class SnapshotProvider
{
    public function __construct(
        private readonly ErgonodeAttributeProviderFactory $sourceFactory,
        private readonly MagentoAttributeProviderFactory $targetFactory,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilder $stateBuilder,
        private readonly OptionSnapshotProvider $optionSnapshotProvider
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        // Fresh providers prevent an operation's earlier reads from hiding its changes.
        $sourceProvider = $this->sourceFactory->create();
        $targetProvider = $this->targetFactory->create();
        $sourceAttributes = $sourceProvider->getAttributeMap();
        $targetAttributes = $targetProvider->getAttributeMap();
        $source = $this->normalize($sourceAttributes);
        $target = $this->normalize($targetAttributes);
        $mappings = $this->stateBuilder->attributes(
            $this->mappingReader->getAttributeRows(),
            $sourceAttributes,
            $targetAttributes
        );
        foreach ($mappings as $mapping) {
            $left = $mapping['left'];
            $right = $mapping['right'];
            foreach (['source' => $left, 'target' => $right] as $side => $attribute) {
                if ($attribute === null) {
                    continue;
                }
                if ($side === 'source') {
                    $source[$attribute['code']] ??= $this->normalizeAttribute($attribute);
                } else {
                    $target[$attribute['code']] ??= $this->normalizeAttribute($attribute);
                }
            }
            if ($left !== null && $right !== null) {
                $source[$left['code']]['mapped_code'] = $right['code'];
                $target[$right['code']]['mapped_code'] = $left['code'];
            }
        }
        ksort($source);
        ksort($target);

        return [
            'source' => array_values($source), 'target' => array_values($target),
            'options' => $this->optionSnapshotProvider->getState($sourceAttributes, $targetAttributes, $mappings),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>> $attributes
     * @return array<string, array<string, mixed>>
     */
    private function normalize(array $attributes): array
    {
        return array_map($this->normalizeAttribute(...), $attributes);
    }

    /**
     * @param  array<string, mixed> $attribute
     * @return array{code: string, label: string, type: string, scope: string, active: bool, mapped_code: string|null}
     */
    private function normalizeAttribute(array $attribute): array
    {
        return [
            'code' => (string)$attribute['code'],
            'label' => (string)$attribute['label'],
            'type' => (string)$attribute['type'],
            'scope' => (string)$attribute['scope'],
            'active' => (bool)($attribute['active'] ?? true),
            'mapped_code' => null,
        ];
    }
}
