<?php

declare(strict_types=1);

namespace Ergonode\Template\Model;

use Magento\Framework\Filter\TranslitUrl;

class TemplateCodeNormalizer
{
    private const int MAX_LENGTH = 128;

    public function __construct(
        private readonly TranslitUrl $translitUrl
    ) {
    }

    public function normalize(string $value): string
    {
        $value = str_replace(['ł', 'Ł'], ['l', 'L'], trim($value));
        $value = mb_strtolower((string)$this->translitUrl->filter($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return mb_substr(trim($value, '_'), 0, self::MAX_LENGTH);
    }
}
