<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Normalizer;

class CategoryCodeGenerator
{
    /** @param string[] $pathLabels */
    public function generate(array $pathLabels): string
    {
        $segments = array_values(array_filter(array_map(
            static function (string $label): string {
                $segment = Normalizer::normalize($label, Normalizer::FORM_KD);
                $segment = strtr($segment !== false ? $segment : $label, [
                    'Ł' => 'L', 'ł' => 'l', 'Đ' => 'D', 'đ' => 'd', 'Ø' => 'O', 'ø' => 'o',
                    'Þ' => 'Th', 'þ' => 'th', 'Æ' => 'Ae', 'æ' => 'ae', 'Œ' => 'Oe', 'œ' => 'oe',
                    'ß' => 'ss',
                ]);
                $segment = strtolower((string)preg_replace('/\p{Mn}+/u', '', $segment));

                return trim((string)preg_replace('/[^a-z0-9]+/', '_', $segment), '_');
            },
            $pathLabels
        ), static fn (string $segment): bool => $segment !== ''));

        return substr(implode('__', $segments), 0, 128);
    }
}
