<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

class AttributeMappingSuggester
{
    public function __construct(
        private readonly ProductAttributeMappingCompatibility $typeCompatibility,
        private readonly AutomaticAttributeMappingPolicy $automaticMappingPolicy
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>> $ergonodeMetadata
     * @param  array<string, array<string, mixed>> $magentoMetadata
     * @param  array<int, array<string, mixed>>    $mappings
     * @param  array<int, array<string, mixed>>    $visibility
     * @return array{
     *     matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array
     * }
     */
    public function suggest(
        array $ergonodeMetadata,
        array $magentoMetadata,
        array $mappings,
        array $visibility = []
    ): array {
        $visibilityMap = $this->buildVisibilityMap($visibility);
        $ergonodeAttributes = $this->buildAttributeIndex(
            $ergonodeMetadata,
            'ergo',
            $visibilityMap
        );
        $magentoAttributes = $this->buildAttributeIndex(
            $magentoMetadata,
            'magento',
            $visibilityMap
        );
        $usedErgonode = [];
        $usedMagento = [];
        $matches = [];
        $conflicts = [];

        foreach ($mappings as $mapping) {
            $left = $this->mappingSide($mapping, 'left');
            $right = $this->mappingSide($mapping, 'right');
            $leftCode = $this->normalizeCode((string)($left['code'] ?? ''));
            $rightCode = $this->normalizeCode((string)($right['code'] ?? ''));

            if ($leftCode !== '') {
                $usedErgonode[$leftCode] = true;
            }
            if ($rightCode !== '') {
                $usedMagento[$rightCode] = true;
            }
        }

        foreach ($mappings as $mapping) {
            $left = $this->mappingSide($mapping, 'left');
            $right = $this->mappingSide($mapping, 'right');

            if (($left === null) === ($right === null)) {
                continue;
            }

            $code = $this->normalizeCode((string)(($left ?? $right)['code'] ?? ''));
            $leftCandidate = $left !== null ? ($ergonodeAttributes[$code] ?? null) : null;
            $rightCandidate = $right !== null ? ($magentoAttributes[$code] ?? null) : null;

            if ($left !== null && !isset($usedMagento[$code])) {
                $leftCandidate = $ergonodeAttributes[$code] ?? null;
                $rightCandidate = $magentoAttributes[$code] ?? null;
            } elseif ($right !== null && !isset($usedErgonode[$code])) {
                $leftCandidate = $ergonodeAttributes[$code] ?? null;
                $rightCandidate = $magentoAttributes[$code] ?? null;
            } else {
                continue;
            }

            $this->addCandidate(
                $code,
                $leftCandidate,
                $rightCandidate,
                $usedErgonode,
                $usedMagento,
                $matches,
                $conflicts,
                $left !== null,
                $right !== null
            );
        }

        foreach (array_keys($ergonodeAttributes) as $code) {
            if (isset($usedErgonode[$code], $usedMagento[$code])) {
                continue;
            }

            $this->addCandidate(
                $code,
                $ergonodeAttributes[$code],
                $magentoAttributes[$code] ?? null,
                $usedErgonode,
                $usedMagento,
                $matches,
                $conflicts
            );
        }

        return [
            'matches' => $matches,
            'conflicts' => array_values($conflicts),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>> $visibility
     * @return array<string, array<string, bool>>
     */
    private function buildVisibilityMap(array $visibility): array
    {
        $result = [];

        foreach ($visibility as $item) {
            $source = (string)($item['source'] ?? '');
            $code = $this->normalizeCode((string)($item['code'] ?? $item['identifier'] ?? ''));
            if (!in_array($source, ['ergo', 'magento'], true) || $code === '') {
                continue;
            }

            $result[$source][$code] = !empty($item['active']);
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>> $attributes
     * @param  array<string, array<string, bool>>  $visibility
     * @return array<string, array<string, mixed>>
     */
    private function buildAttributeIndex(array $attributes, string $source, array $visibility): array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            $code = $this->normalizeCode((string)($attribute['code'] ?? ''));
            if (!$this->automaticMappingPolicy->isAllowed($code)) {
                continue;
            }

            $attribute['source'] = $source;
            $attribute['active'] = $visibility[$source][$code] ?? !empty($attribute['active']);
            $result[$code] = $attribute;
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<string, mixed> $mapping
     * @return array<string, mixed>|null
     */
    private function mappingSide(array $mapping, string $side): ?array
    {
        return isset($mapping[$side]) && is_array($mapping[$side]) ? $mapping[$side] : null;
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed>|null $right
     * @param array<string, bool> $usedErgonode
     * @param array<string, bool> $usedMagento
     * @param array<int, array{left: array<string, mixed>, right: array<string, mixed>}> $matches
     * @param array<string, array{code: string, ergonode_type: string, magento_type: string, reason: string}> $conflicts
     */
    private function addCandidate(
        string $code,
        ?array $left,
        ?array $right,
        array &$usedErgonode,
        array &$usedMagento,
        array &$matches,
        array &$conflicts,
        bool $allowUsedErgonode = false,
        bool $allowUsedMagento = false
    ): void {
        if ($code === '' || $left === null || $right === null) {
            return;
        }
        if ((!$allowUsedErgonode && isset($usedErgonode[$code]))
            || (!$allowUsedMagento && isset($usedMagento[$code]))
        ) {
            return;
        }
        if (empty($left['active']) || empty($right['active'])) {
            return;
        }

        $ergonodeType = strtolower(trim((string)($left['type'] ?? '')));
        $magentoType = strtolower(trim((string)($right['type'] ?? '')));
        if (!$this->typeCompatibility->canMapAttributes($ergonodeType, $magentoType, $code)) {
            $conflicts[$code] = [
                'code' => $code,
                'ergonode_type' => $ergonodeType,
                'magento_type' => $magentoType,
                'reason' => 'incompatible_types',
            ];

            return;
        }

        $matches[] = ['left' => $left, 'right' => $right];
        $usedErgonode[$code] = true;
        $usedMagento[$code] = true;
    }

    private function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
