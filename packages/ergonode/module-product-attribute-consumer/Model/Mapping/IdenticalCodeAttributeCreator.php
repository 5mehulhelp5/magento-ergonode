<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\ProductAttribute\Model\Mapping\AutomaticAttributeMappingPolicy;

use Ergonode\AttributeConsumer\Api\ErgonodeAttributeProviderInterface;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Magento\Framework\Exception\LocalizedException;

class IdenticalCodeAttributeCreator
{
    public function __construct(
        private readonly ErgonodeAttributeProviderInterface $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly ProductAttributeMappingCompatibility $typeCompatibility,
        private readonly AutomaticAttributeMappingPolicy $automaticMappingPolicy,
        private readonly MagentoAttributeCreator $magentoAttributeCreator
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @return array{
     *     matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array<int, array{code: string, ergonode_type: string, magento_type: string, reason: string}>,
     *     created: int
     * }
     * @throws LocalizedException
     */
    public function create(array $mappings): array
    {
        $usedErgonode = [];
        $usedMagento = [];
        foreach ($mappings as $mapping) {
            $left = $this->mappingSide($mapping, 'left');
            $right = $this->mappingSide($mapping, 'right');
            $leftCode = $this->normalizeCode((string)($left['code'] ?? ''));
            $rightCode = $this->normalizeCode((string)($right['code'] ?? ''));

            if ($leftCode !== '' && $right !== null) {
                $usedErgonode[$leftCode] = true;
            }
            if ($rightCode !== '') {
                $usedMagento[$rightCode] = true;
            }
        }

        $ergonodeAttributes = $this->buildAttributeIndex(
            $this->ergonodeAttributeProvider->getAttributeMap(),
            'ergo',
            []
        );
        $existingMagentoAttributes = $this->magentoAttributeProvider->getAttributeMap(true);
        $matches = [];
        $conflicts = [];
        $created = 0;

        foreach ($ergonodeAttributes as $code => $left) {
            if (isset($usedErgonode[$code])
                || isset($usedMagento[$code])
                || isset($existingMagentoAttributes[$code])
                || empty($left['active'])
            ) {
                continue;
            }

            try {
                $preview = $this->magentoAttributeCreator->previewFromErgonodeAttribute($left);
            } catch (LocalizedException) {
                $conflicts[] = $this->creationConflict($code, $left, 'unsupported_creation');

                continue;
            }

            if (empty($preview['created']) || $this->normalizeCode((string)($preview['code'] ?? '')) !== $code) {
                continue;
            }
            if (!$this->typeCompatibility->canMapAttributes(
                (string)($left['type'] ?? ''),
                (string)($preview['type'] ?? ''),
                $code
            )
            ) {
                $conflicts[] = $this->creationConflict(
                    $code,
                    $left,
                    'incompatible_types',
                    (string)($preview['type'] ?? '')
                );

                continue;
            }

            $right = $this->magentoAttributeCreator->createFromErgonodeAttribute($left);
            $matches[] = ['left' => $left, 'right' => $right];
            $usedErgonode[$code] = true;
            $usedMagento[$code] = true;
            if (!empty($right['created'])) {
                $created++;
            }
        }

        if ($created > 0) {
            $this->magentoAttributeProvider->clearCache();
        }

        return ['matches' => $matches, 'conflicts' => $conflicts, 'created' => $created];
    }

    /**
     * @param  array<string, mixed> $attribute
     * @return array{code: string, ergonode_type: string, magento_type: string, reason: string}
     */
    private function creationConflict(
        string $code,
        array $attribute,
        string $reason,
        string $magentoType = ''
    ): array {
        return [
            'code' => $code,
            'ergonode_type' => strtolower(trim((string)($attribute['type'] ?? ''))),
            'magento_type' => strtolower(trim($magentoType)),
            'reason' => $reason,
        ];
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

    private function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
