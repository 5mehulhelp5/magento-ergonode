<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Sync;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;

class OptionLabelResolver
{
    public function __construct(
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function requireAdminLanguageCode(): string
    {
        return $this->languageMappingProvider->requireAdminLanguageCode();
    }

    /** @return array<int, string> */
    public function getLanguageStoreMap(): array
    {
        return $this->languageMappingProvider->getLanguageStoreMap();
    }

    /** @return array<string, string> */
    public function decode(string $labelsJson): array
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            return [];
        }
        if (!is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $language => $label) {
            $language = (string)$language;
            $label = $this->truncate((string)$label);
            if ($language !== '' && $label !== '') {
                $result[$language] = $label;
            }
        }

        return $result;
    }

    /** @param array<string, string> $labels */
    public function resolveDefault(array $labels, string $fallback): string
    {
        return $this->truncate((string)($labels[$this->requireAdminLanguageCode()]
            ?? reset($labels)
            ?: $fallback));
    }

    private function truncate(string $label): string
    {
        return mb_substr(trim($label), 0, 255);
    }
}
