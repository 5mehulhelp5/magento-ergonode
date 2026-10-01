<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

use Magento\Framework\Exception\LocalizedException;

interface ErgonodeLanguageCodesRefresherInterface
{
    /**
     * @return string[]
     * @throws LocalizedException
     */
    public function refreshErgonodeLanguageCodes(): array;
}
