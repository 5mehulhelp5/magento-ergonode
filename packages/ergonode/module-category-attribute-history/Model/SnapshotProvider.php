<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model;

use Ergonode\CategoryAttributeHistory\Api\SourceSnapshotProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterfaceFactory;

class SnapshotProvider
{
    public function __construct(
        private readonly SourceSnapshotProviderInterface $sourceProvider,
        private readonly MagentoAttributeProviderInterfaceFactory $targetFactory,
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilderInterface $stateBuilder
    ) {
    }

    /**
     * @return array{source: list<array<string, mixed>>, target: list<array<string, mixed>>}
     */
    public function getState(): array
    {
        // Fresh providers prevent an operation's earlier reads from hiding its changes.
        $targetProvider = $this->targetFactory->create();
        $sourceAttributes = $this->sourceProvider->getAttributeMap();
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
                    $source[$attribute['code']]['is_draft'] = $left === null || $right === null;
                } else {
                    $target[$attribute['code']] ??= $this->normalizeAttribute($attribute);
                    $target[$attribute['code']]['is_draft'] = $left === null || $right === null;
                }
            }
            if ($left !== null && $right !== null) {
                $source[$left['code']]['mapped_code'] = $right['code'];
                $target[$right['code']]['mapped_code'] = $left['code'];
            }
        }
        ksort($source);
        ksort($target);

        return ['source' => array_values($source), 'target' => array_values($target)];
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
     * @return array{
     *     code: string, label: string, type: string, scope: string,
     *     active: bool, mapped_code: string|null, is_draft: bool
     * }
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
            'is_draft' => false,
        ];
    }
}
