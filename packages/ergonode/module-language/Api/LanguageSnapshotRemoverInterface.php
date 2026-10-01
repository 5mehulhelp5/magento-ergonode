<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

use Magento\Framework\Exception\LocalizedException;

interface LanguageSnapshotRemoverInterface
{
    /**
     * @param string $languageCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(string $languageCode): void;
}
