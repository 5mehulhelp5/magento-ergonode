<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Model;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

class TemplateNameResolver
{
    public function __construct(
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function resolve(string $rawJson, string $fallback): string
    {
        $names = $this->names($rawJson);
        $locale = $this->languageMappingProvider->getAdminLanguageCode();
        foreach ($names as $name) {
            if ($locale !== null && (string)($name['language'] ?? '') === $locale) {
                $value = trim((string)($name['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $this->resolveAll($rawJson)[0] ?? $fallback;
    }

    /** @return list<string> */
    public function resolveAll(string $rawJson): array
    {
        $resolved = [];
        foreach ($this->names($rawJson) as $name) {
            $value = trim((string)($name['value'] ?? ''));
            if ($value !== '') {
                $resolved[$value] = true;
            }
        }

        return array_keys($resolved);
    }

    /** @return array<int, array<string, mixed>> */
    private function names(string $rawJson): array
    {
        try {
            $template = $this->json->unserialize($rawJson);
        } catch (Throwable) {
            return [];
        }

        return is_array($template) && isset($template['name']) && is_array($template['name'])
            ? array_values(array_filter($template['name'], 'is_array'))
            : [];
    }
}
