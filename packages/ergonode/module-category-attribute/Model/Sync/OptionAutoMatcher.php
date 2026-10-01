<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\Sync;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionAutoMatcher implements OptionAutoMatcherInterface
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly OptionMatchKeyResolver $matchKeyResolver
    ) {
    }

    public function suggest(
        int $attributeMappingId,
        array $ergonodeOptions,
        array $magentoOptions
    ): array {
        $attributeMapping = $this->mappingReader->getAttributeRow($attributeMappingId);
        if ($attributeMapping === null) {
            throw new LocalizedException(__('Attribute mapping "%1" does not exist.', $attributeMappingId));
        }
        if ((string)($attributeMapping['status'] ?? '') !== 'complete') {
            throw new LocalizedException(__('Options require a saved category attribute mapping.'));
        }
        if (!$this->typeCompatibility->canMapOptions(
            (string)($attributeMapping['ergonode_type'] ?? ''),
            (string)($attributeMapping['magento_type'] ?? '')
        )
        ) {
            throw new LocalizedException(__('Options can be matched only for option-mappable attribute pairs.'));
        }

        $ergonodeOptions = $this->validOptions($ergonodeOptions);
        $magentoOptions = $this->validOptions($magentoOptions);
        $magentoByKey = [];
        foreach ($magentoOptions as $index => $option) {
            $key = $this->matchKeyResolver->resolve($attributeMapping, $option, 'magento');
            if ($key !== '') {
                $magentoByKey[$key][] = ['index' => $index, 'option' => $option];
            }
        }

        $matches = [];
        $usedErgonode = [];
        $usedMagento = [];
        $this->matchPass(
            $attributeMapping,
            $ergonodeOptions,
            $magentoByKey,
            true,
            $usedErgonode,
            $usedMagento,
            $matches
        );
        $this->matchPass(
            $attributeMapping,
            $ergonodeOptions,
            $magentoByKey,
            false,
            $usedErgonode,
            $usedMagento,
            $matches
        );
        ksort($matches);

        return ['matches' => array_values($matches)];
    }

    /**
     * @param array<string, mixed>                                                       $attributeMapping
     * @param array<int, array<string, mixed>>                                           $ergonodeOptions
     * @param array<string, array<int, array{index: int, option: array<string, mixed>}>> $magentoByKey
     * @param array<int, bool>                                                           $usedErgonode
     * @param array<int, bool>                                                           $usedMagento
     * @param array<int, array{left: array<string, mixed>, right: array<string, mixed>}> $matches
     */
    private function matchPass(
        array $attributeMapping,
        array $ergonodeOptions,
        array $magentoByKey,
        bool $primary,
        array &$usedErgonode,
        array &$usedMagento,
        array &$matches
    ): void {
        $offsets = [];
        foreach ($ergonodeOptions as $leftIndex => $option) {
            if (isset($usedErgonode[$leftIndex])) {
                continue;
            }
            $key = $primary
                ? $this->matchKeyResolver->resolvePrimary($attributeMapping, $option, 'ergonode')
                : $this->matchKeyResolver->resolve($attributeMapping, $option, 'ergonode');
            if ($key === '') {
                continue;
            }

            $offset = $offsets[$key] ?? 0;
            while (isset($magentoByKey[$key][$offset])
                && isset($usedMagento[$magentoByKey[$key][$offset]['index']])
            ) {
                $offset++;
            }
            $offsets[$key] = $offset;
            if (!isset($magentoByKey[$key][$offset])) {
                continue;
            }

            $candidate = $magentoByKey[$key][$offset];
            $matches[$leftIndex] = ['left' => $option, 'right' => $candidate['option']];
            $usedErgonode[$leftIndex] = true;
            $usedMagento[$candidate['index']] = true;
            $offsets[$key] = $offset + 1;
        }
    }

    /**
     * @param  array<int, array<string, mixed>> $options
     * @return array<int, array<string, mixed>>
     */
    private function validOptions(array $options): array
    {
        return array_values(
            array_filter(
                $options,
                static fn (mixed $option): bool => is_array($option)
                && trim((string)($option['code'] ?? '')) !== ''
            )
        );
    }
}
