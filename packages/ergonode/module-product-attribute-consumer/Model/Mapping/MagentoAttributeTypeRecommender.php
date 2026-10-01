<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeOptionProvider;

class MagentoAttributeTypeRecommender
{
    /** @var array<string, string> */
    private array $recommendations = [];

    public function __construct(
        private readonly AttributeCacheRefresherInterface $attributeCacheRefresher,
        private readonly ErgonodeOptionProvider $ergonodeOptionProvider,
        private readonly BooleanValueNormalizer $booleanValueNormalizer
    ) {
    }

    public function recommend(string $attributeCode, string $ergonodeType): string
    {
        $ergonodeType = strtolower(trim($ergonodeType));
        if ($ergonodeType !== 'select') {
            return $ergonodeType;
        }

        if (isset($this->recommendations[$attributeCode])) {
            return $this->recommendations[$attributeCode];
        }

        $this->attributeCacheRefresher->refreshOptions($attributeCode);
        $options = $this->ergonodeOptionProvider->getOptionDefinitions($attributeCode);

        return $this->recommendations[$attributeCode] = $this->isBooleanOptionSet($options)
            ? 'boolean'
            : 'select';
    }

    /**
     * @param array<int, array{code: string, labels: array<string, string>}> $options
     */
    private function isBooleanOptionSet(array $options): bool
    {
        if ($options === [] || count($options) > 2) {
            return false;
        }

        $optionValues = [];
        foreach ($options as $option) {
            $values = [];
            $codeValue = $this->booleanValueNormalizer->normalizeToMagentoValue($option['code']);
            if ($codeValue !== null) {
                $values[] = $codeValue;
            }

            foreach ($option['labels'] as $languageCode => $label) {
                $labelValue = $this->booleanValueNormalizer->normalizeToMagentoValue($label, $languageCode);
                if ($labelValue === null) {
                    return false;
                }

                $values[] = $labelValue;
            }

            $values = array_values(array_unique($values));
            if (count($values) !== 1) {
                return false;
            }

            $optionValues[] = $values[0];
        }

        return count(array_unique($optionValues)) === count($optionValues);
    }
}
