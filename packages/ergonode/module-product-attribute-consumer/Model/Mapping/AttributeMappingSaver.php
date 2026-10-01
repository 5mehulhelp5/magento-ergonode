<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingNormalizer;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver as MappingSaver;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoAttributeCreator;
use Magento\Framework\Exception\LocalizedException;

class AttributeMappingSaver
{
    public function __construct(
        private readonly AttributeMappingPreparer $preparer,
        private readonly AttributeMappingNormalizer $normalizer,
        private readonly MagentoAttributeCreator $magentoAttributeCreator,
        private readonly MappingSaver $mappingSaver
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function save(array $mappings, array $visibility): array
    {
        $pending = [];
        $prepared = $this->preparer->prepare($mappings, $pending);
        $this->normalizer->normalize($prepared);
        foreach ($pending as $attribute) {
            $this->magentoAttributeCreator->createFromErgonodeAttribute($attribute);
        }

        return $this->mappingSaver->save($prepared, $visibility);
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function saveAdditions(array $mappings): array
    {
        $pending = [];
        $prepared = $this->preparer->prepare($mappings, $pending);
        if ($pending !== []) {
            throw new LocalizedException(__('Automatic mapping cannot create Magento attributes.'));
        }

        return $this->mappingSaver->saveAdditions($prepared);
    }
}
