<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

class OptionLabelKeyNormalizer
{
    public function normalize(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($ascii) && $ascii !== '') {
            $label = $ascii;
        }

        return preg_replace('/[^a-z0-9]+/', '', $label) ?? '';
    }
}
