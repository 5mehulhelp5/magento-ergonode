<?php

declare(strict_types=1);

namespace Ergonode\Language\Model;

use Ergonode\Language\Api\LanguageCodeNormalizerInterface;

class LanguageCodeNormalizer implements LanguageCodeNormalizerInterface
{
    public function normalize(string $code): string
    {
        return strtolower(str_replace('-', '_', trim($code)));
    }
}
