<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Source;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;

class SystemOptionDefinition
{
    private const array OPTIONS = [
        'boolean' => [0 => ['no', 1], 1 => ['yes', 0]],
        'status' => [1 => ['enabled', 0], 2 => ['disabled', 1]],
        'visibility' => [
            1 => ['not_visible_individually', 2],
            2 => ['catalog', 3],
            3 => ['search', 4],
            4 => ['catalog_search', 5],
        ],
    ];

    public function __construct(
        private readonly SystemOptionDictionary $dictionary,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    /** @return array{code: string, names: array<string, string>}|null */
    public function get(ProductAttributeInterface $attribute, int $optionId): ?array
    {
        $type = (string)$attribute->getAttributeCode();
        if ($type !== 'status' && $type !== 'visibility') {
            $isBoolean = $attribute->getFrontendInput() === 'boolean'
                || ltrim((string)$attribute->getSourceModel(), '\\') === Boolean::class;
            $type = $isBoolean ? 'boolean' : '';
        }
        $option = self::OPTIONS[$type][$optionId] ?? null;
        if ($option === null) {
            return null;
        }
        $names = [];
        foreach ($this->languageMappingProvider->getLanguageCodes() as $language) {
            $names[$language] = $this->dictionary->translate($option[1], $language);
        }
        ksort($names);

        return ['code' => $option[0], 'names' => $names];
    }
}
