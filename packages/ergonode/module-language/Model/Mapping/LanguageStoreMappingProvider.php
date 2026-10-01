<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use Ergonode\Language\Api\LanguageMappingStateProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Exception\AdminLanguageMappingRequiredException;
use Ergonode\Language\Exception\NoActiveLanguageMappingException;

class LanguageStoreMappingProvider implements LanguageStoreMappingProviderInterface
{
    public function __construct(
        private readonly LanguageMappingStateProviderInterface $stateProvider,
        private readonly MappingCache $cache
    ) {
    }

    public function getLanguageStoreMap(): array
    {
        return $this->requireMap($this->cache->get(function (): array {
            $state = $this->stateProvider->getState();
            $languages = array_fill_keys($state->codes, true);
            $map = [];
            foreach ($state->rows as $row) {
                $storeId = $row['store_id'];
                $code = $row['language_code'];
                if ($storeId === null || $code === null || !isset($state->stores[$storeId], $languages[$code])) {
                    continue;
                }
                if (($state->storeVisibility[(string)$storeId] ?? true)
                    && ($state->languageVisibility[$code] ?? true)
                ) {
                    $map[$storeId] = $code;
                }
            }
            return $map;
        }));
    }

    public function getLanguageForStoreId(int $storeId): ?string
    {
        return $this->getLanguageStoreMap()[$storeId] ?? null;
    }

    public function getStoreIdsByLanguageCode(): array
    {
        $result = [];

        foreach ($this->getLanguageStoreMap() as $storeId => $languageCode) {
            $result[$languageCode] ??= [];
            $result[$languageCode][] = $storeId;
        }

        ksort($result);

        return $result;
    }

    public function getLanguageCodes(): array
    {
        return array_values(array_unique($this->getLanguageStoreMap()));
    }

    public function getAdminLanguageCode(): ?string
    {
        try {
            return $this->getLanguageStoreMap()[0] ?? null;
        } catch (NoActiveLanguageMappingException) {
            return null;
        }
    }

    public function requireAdminLanguageCode(): string
    {
        $languageCode = $this->getLanguageStoreMap()[0] ?? null;
        if ($languageCode === null) {
            throw new AdminLanguageMappingRequiredException();
        }

        return $languageCode;
    }

    /**
     * @param array<int, string> $map
     * @return non-empty-array<int, string>
     * @throws NoActiveLanguageMappingException
     */
    private function requireMap(array $map): array
    {
        if ($map === []) {
            throw new NoActiveLanguageMappingException();
        }

        return $map;
    }
}
