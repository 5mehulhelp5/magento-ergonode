<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Normalizer as UnicodeNormalizer;

class BooleanValueNormalizer
{
    private const array UNIVERSAL_VALUES = [
        '0' => 0,
        '1' => 1,
        'false' => 0,
        'true' => 1,
    ];

    private const array VALUES_BY_LANGUAGE = [
        'ar' => ['نعم' => 1, 'لا' => 0],
        'ast' => ['sí' => 1, 'si' => 1, 's' => 1, 'non' => 0, 'n' => 0],
        'az' => ['hə' => 1, 'he' => 1, 'h' => 1, 'yox' => 0, 'y' => 0],
        'be' => ['так' => 1, 'т' => 1, 'не' => 0, 'н' => 0],
        'bg' => ['да' => 1, 'д' => 1, 'не' => 0, 'н' => 0],
        'br' => ['ya' => 1, 'y' => 1, 'ket' => 0, 'k' => 0],
        'bs' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0],
        'ca' => ['sí' => 1, 'si' => 1, 's' => 1, 'no' => 0, 'n' => 0],
        'cnr' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0],
        'co' => ['iè' => 1, 'ie' => 1, 'i' => 1, 'nò' => 0, 'no' => 0, 'n' => 0],
        'cs' => ['ano' => 1, 'a' => 1, 'ne' => 0, 'n' => 0],
        'cy' => ['ie' => 1, 'i' => 1, 'na' => 0, 'n' => 0],
        'da' => ['ja' => 1, 'j' => 1, 'nej' => 0, 'n' => 0],
        'de' => ['ja' => 1, 'j' => 1, 'nein' => 0, 'n' => 0],
        'el' => ['ναι' => 1, 'όχι' => 0],
        'en' => ['yes' => 1, 'y' => 1, 'no' => 0, 'n' => 0],
        'es' => ['sí' => 1, 'si' => 1, 's' => 1, 'no' => 0, 'n' => 0],
        'et' => ['jah' => 1, 'j' => 1, 'ei' => 0, 'e' => 0],
        'eu' => ['bai' => 1, 'b' => 1, 'ez' => 0, 'e' => 0],
        'fi' => ['kyllä' => 1, 'kylla' => 1, 'k' => 1, 'ei' => 0, 'e' => 0],
        'fo' => ['já' => 1, 'ja' => 1, 'j' => 1, 'nei' => 0, 'n' => 0],
        'fr' => ['oui' => 1, 'o' => 1, 'non' => 0, 'n' => 0],
        'fur' => ['sì' => 1, 'si' => 1, 's' => 1, 'no' => 0, 'n' => 0],
        'fy' => ['ja' => 1, 'j' => 1, 'nee' => 0, 'n' => 0],
        'ga' => ['tá' => 1, 'ta' => 1, 'ní' => 0, 'ni' => 0],
        'gd' => ['tha' => 1, 't' => 1, 'chaneil' => 0, 'c' => 0],
        'gl' => ['si' => 1, 's' => 1, 'non' => 0, 'n' => 0],
        'he' => ['כן' => 1, 'לא' => 0],
        'hi' => ['हाँ' => 1, 'हां' => 1, 'नहीं' => 0],
        'hr' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0],
        'hu' => ['igen' => 1, 'i' => 1, 'nem' => 0, 'n' => 0],
        'hy' => ['այո' => 1, 'ա' => 1, 'ոչ' => 0, 'ո' => 0],
        'is' => ['já' => 1, 'ja' => 1, 'j' => 1, 'nei' => 0, 'n' => 0],
        'it' => ['sì' => 1, 'si' => 1, 's' => 1, 'no' => 0, 'n' => 0],
        'ja' => ['はい' => 1, 'いいえ' => 0],
        'ka' => ['კი' => 1, 'კ' => 1, 'არა' => 0, 'ა' => 0],
        'kk' => ['иә' => 1, 'и' => 1, 'жоқ' => 0, 'ж' => 0],
        'ko' => ['예' => 1, '네' => 1, '아니요' => 0],
        'la' => ['ita' => 1, 'i' => 1, 'minime' => 0, 'm' => 0],
        'lb' => ['jo' => 1, 'j' => 1, 'nee' => 0, 'n' => 0],
        'lt' => ['taip' => 1, 't' => 1, 'ne' => 0, 'n' => 0],
        'lv' => ['jā' => 1, 'ja' => 1, 'j' => 1, 'nē' => 0, 'ne' => 0, 'n' => 0],
        'mk' => ['да' => 1, 'д' => 1, 'не' => 0, 'н' => 0],
        'mo' => ['da' => 1, 'd' => 1, 'nu' => 0, 'n' => 0],
        'mt' => ['iva' => 1, 'i' => 1, 'le' => 0, 'l' => 0],
        'nb' => ['ja' => 1, 'j' => 1, 'nei' => 0, 'n' => 0],
        'nl' => ['ja' => 1, 'j' => 1, 'nee' => 0, 'n' => 0],
        'nn' => ['ja' => 1, 'j' => 1, 'nei' => 0, 'n' => 0],
        'no' => ['ja' => 1, 'j' => 1, 'nei' => 0, 'n' => 0],
        'oc' => ['òc' => 1, 'oc' => 1, 'o' => 1, 'non' => 0, 'n' => 0],
        'pl' => ['tak' => 1, 't' => 1, 'nie' => 0, 'n' => 0],
        'pt' => ['sim' => 1, 's' => 1, 'não' => 0, 'nao' => 0, 'n' => 0],
        'rm' => ['gea' => 1, 'g' => 1, 'na' => 0, 'n' => 0],
        'ro' => ['da' => 1, 'd' => 1, 'nu' => 0, 'n' => 0],
        'ru' => ['да' => 1, 'д' => 1, 'нет' => 0, 'н' => 0],
        'sc' => ['eja' => 1, 'e' => 1, 'nono' => 0, 'n' => 0],
        'se' => ['jo' => 1, 'j' => 1, 'ii' => 0, 'i' => 0],
        'sh' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0],
        'sk' => ['áno' => 1, 'ano' => 1, 'a' => 1, 'nie' => 0, 'n' => 0],
        'sl' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0],
        'sq' => ['po' => 1, 'p' => 1, 'jo' => 0, 'j' => 0],
        'sr' => ['da' => 1, 'd' => 1, 'ne' => 0, 'n' => 0, 'да' => 1, 'не' => 0],
        'sv' => ['ja' => 1, 'j' => 1, 'nej' => 0, 'n' => 0],
        'tr' => ['evet' => 1, 'e' => 1, 'hayır' => 0, 'hayir' => 0, 'h' => 0],
        'uk' => ['так' => 1, 'т' => 1, 'ні' => 0, 'н' => 0],
        'yi' => ['יאָ' => 1, 'י' => 1, 'קײן' => 0, 'ק' => 0],
        'zh' => ['是' => 1, '是的' => 1, '否' => 0, '不是' => 0],
    ];

    public function normalizeToMagentoValue(mixed $value, ?string $languageCode = null): ?int
    {
        if (is_bool($value)) {
            return (int)$value;
        }

        $normalized = $this->normalizeToken($value);
        if (isset(self::UNIVERSAL_VALUES[$normalized])) {
            return self::UNIVERSAL_VALUES[$normalized];
        }

        $language = $this->normalizeLanguageCode($languageCode);
        if ($language !== '') {
            return self::VALUES_BY_LANGUAGE[$language][$normalized] ?? null;
        }

        $legacyValues = self::VALUES_BY_LANGUAGE['en'] + self::VALUES_BY_LANGUAGE['pl'];

        return $legacyValues[$normalized] ?? null;
    }

    private function normalizeToken(mixed $value): string
    {
        $rawValue = (string)$value;
        $value = UnicodeNormalizer::normalize($rawValue, UnicodeNormalizer::FORM_C) ?: $rawValue;
        $value = mb_strtolower(trim($value), 'UTF-8');

        return preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $value) ?? '';
    }

    private function normalizeLanguageCode(?string $languageCode): string
    {
        $languageCode = strtolower(trim((string)$languageCode));
        $parts = preg_split('/[-_]/', $languageCode);

        return (string)($parts[0] ?? '');
    }
}
