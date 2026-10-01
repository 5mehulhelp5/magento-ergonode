<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Template;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

class TemplateSectionNameResolver
{
    public function __construct(
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function resolve(string $rawJson, string $fallback): string
    {
        try {
            $section = $this->json->unserialize($rawJson);
        } catch (Throwable) {
            return $fallback;
        }

        if (!is_array($section)) {
            return $fallback;
        }

        $names = $section['name'] ?? [];
        if (is_string($names)) {
            return trim($names) !== '' ? trim($names) : $fallback;
        }
        if (!is_array($names)) {
            return $fallback;
        }

        $defaultLocale = $this->languageMappingProvider->getAdminLanguageCode();
        $firstName = '';
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }

            $value = trim((string)($name['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $firstName = $firstName !== '' ? $firstName : $value;
            if ($defaultLocale !== null && (string)($name['language'] ?? '') === $defaultLocale) {
                return $value;
            }
        }

        return $firstName !== '' ? $firstName : $fallback;
    }

    /**
     * @return list<string>
     */
    public function resolveAll(string $rawJson): array
    {
        try {
            $section = $this->json->unserialize($rawJson);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($section)) {
            return [];
        }

        $names = $section['name'] ?? [];
        if (is_string($names)) {
            $name = trim($names);

            return $name !== '' ? [$name] : [];
        }
        if (!is_array($names)) {
            return [];
        }

        $resolved = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }

            $value = trim((string)($name['value'] ?? ''));
            if ($value !== '') {
                $resolved[$value] = true;
            }
        }

        return array_keys($resolved);
    }
}
