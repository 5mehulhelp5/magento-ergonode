<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

interface ErgonodeLanguageCodesProviderInterface
{
    /**
     * @return string[]
     */
    public function getErgonodeLanguageCodes(): array;
}
