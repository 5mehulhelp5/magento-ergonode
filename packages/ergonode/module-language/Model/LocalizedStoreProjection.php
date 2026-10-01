<?php

declare(strict_types=1);

namespace Ergonode\Language\Model;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class LocalizedStoreProjection
{
    public function __construct(
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    /**
     * @param array<int, string> $storeValues
     * @return array<string, string>
     */
    public function project(string $defaultValue, array $storeValues): array
    {
        $result = [];
        $projectedLanguages = [];
        $defaultValue = trim($defaultValue);
        $storeLanguages = $this->languageMappingProvider->getLanguageStoreMap();
        foreach ($storeLanguages as $storeId => $languageCode) {
            if (isset($projectedLanguages[$languageCode])) {
                continue;
            }
            $projectedLanguages[$languageCode] = true;
            $value = $storeId === 0 ? $defaultValue : trim($storeValues[(int)$storeId] ?? $defaultValue);
            if ($value === '') {
                continue;
            }
            $result[$languageCode] = $value;
        }
        ksort($result);

        return $result;
    }
}
