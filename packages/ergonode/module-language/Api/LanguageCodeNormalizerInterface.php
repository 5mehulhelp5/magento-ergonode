<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

interface LanguageCodeNormalizerInterface
{
    /**
     * Normalizes a language or locale code for comparison.
     *
     * @param string $code
     * @return string
     */
    public function normalize(string $code): string;
}
