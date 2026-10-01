<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

use Ergonode\Language\Exception\AdminLanguageMappingRequiredException;
use Ergonode\Language\Exception\NoActiveLanguageMappingException;

interface LanguageStoreMappingProviderInterface
{
    /**
     * @return non-empty-array<int, string>
     * @throws NoActiveLanguageMappingException
     */
    public function getLanguageStoreMap(): array;

    /**
     * @param int $storeId
     * @return string|null
     * @throws NoActiveLanguageMappingException
     */
    public function getLanguageForStoreId(int $storeId): ?string;

    /**
     * @return array<string, int[]>
     * @throws NoActiveLanguageMappingException
     */
    public function getStoreIdsByLanguageCode(): array;

    /**
     * @return non-empty-list<string>
     * @throws NoActiveLanguageMappingException
     */
    public function getLanguageCodes(): array;

    /** @return string|null */
    public function getAdminLanguageCode(): ?string;

    /**
     * @return string
     * @throws AdminLanguageMappingRequiredException
     */
    public function requireAdminLanguageCode(): string;
}
