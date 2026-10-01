<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Model\Source;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\TranslitUrl;

class OptionCodeGenerator
{
    private const int MAX_LENGTH = 128;

    public function __construct(private readonly TranslitUrl $translitUrl)
    {
    }

    /** @throws LocalizedException */
    public function generate(string $label): string
    {
        $code = str_replace('-', '_', (string)$this->translitUrl->filter(trim($label)));
        $code = rtrim(substr($code, 0, self::MAX_LENGTH), '_');
        if ($code === '') {
            throw new LocalizedException(
                __('Magento option label "%1" cannot be converted to an Ergonode option code.', $label)
            );
        }

        return $code;
    }
}
