<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\ProductAttributeConsumer\Model\Mapping\BooleanValueNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BooleanValueNormalizerTest extends TestCase
{
    #[DataProvider('localizedValuesProvider')]
    public function testNormalizesLocalizedBooleanValues(mixed $value, ?string $languageCode, ?int $expected): void
    {
        self::assertSame(
            $expected,
            (new BooleanValueNormalizer())->normalizeToMagentoValue($value, $languageCode)
        );
    }

    /** @return array<string, array{mixed, string|null, int|null}> */
    public static function localizedValuesProvider(): array
    {
        return [
            'integer zero' => [0, null, 0],
            'string one' => [' 1 ', 'unknown_LANGUAGE', 1],
            'boolean true' => [true, null, 1],
            'polish short true' => [' T ', 'pl_PL', 1],
            'polish long false' => ['NIE', 'pl-PL', 0],
            'english short false' => ['n', 'en_GB', 0],
            'german true' => ['Ja', 'de_DE', 1],
            'french false' => ['Non', 'fr_FR', 0],
            'portuguese false' => ['Não', 'pt_PT', 0],
            'decomposed portuguese false' => ["Na\u{0303}o", 'pt_PT', 0],
            'ukrainian true' => ['Так', 'uk_UA', 1],
            'hindi true' => ['हाँ', 'hi_IN', 1],
            'hindi false' => ['नहीं', 'hi_IN', 0],
            'japanese false' => ['いいえ', 'ja_JP', 0],
            'albanian true' => ['Po', 'sq_AL', 1],
            'albanian false' => ['Jo', 'sq_AL', 0],
            'armenian true' => ['այո', 'hy_AM', 1],
            'armenian false' => ['ոչ', 'hy_AM', 0],
            'asturian true' => ['Sí', 'ast_ES', 1],
            'asturian false' => ['Non', 'ast_ES', 0],
            'azerbaijani true' => ['Hə', 'az_AZ', 1],
            'azerbaijani false' => ['Yox', 'az_AZ', 0],
            'basque true' => ['Bai', 'eu_ES', 1],
            'basque false' => ['Ez', 'eu_ES', 0],
            'belarusian true' => ['Так', 'be_BY', 1],
            'belarusian false' => ['Не', 'be_BY', 0],
            'breton true' => ['Ya', 'br_FR', 1],
            'breton false' => ['Ket', 'br_FR', 0],
            'corsican true' => ['Iè', 'co_FR', 1],
            'corsican false' => ['Nò', 'co_FR', 0],
            'montenegrin true' => ['Da', 'cnr_ME', 1],
            'montenegrin false' => ['Ne', 'cnr_ME', 0],
            'faroese true' => ['Já', 'fo_FO', 1],
            'faroese false' => ['Nei', 'fo_FO', 0],
            'friulian true' => ['Sì', 'fur_IT', 1],
            'friulian false' => ['No', 'fur_IT', 0],
            'frisian true' => ['Ja', 'fy_NL', 1],
            'frisian false' => ['Nee', 'fy_NL', 0],
            'galician true' => ['Si', 'gl_ES', 1],
            'galician false' => ['Non', 'gl_ES', 0],
            'georgian true' => ['კი', 'ka_GE', 1],
            'georgian false' => ['არა', 'ka_GE', 0],
            'kazakh true' => ['Иә', 'kk_KZ', 1],
            'kazakh false' => ['Жоқ', 'kk_KZ', 0],
            'latin true' => ['Ita', 'la_VA', 1],
            'latin false' => ['Minime', 'la_VA', 0],
            'luxembourgish true' => ['Jo', 'lb_LU', 1],
            'luxembourgish false' => ['Nee', 'lb_LU', 0],
            'maltese true' => ['Iva', 'mt_MT', 1],
            'maltese false' => ['Le', 'mt_MT', 0],
            'legacy moldavian true' => ['Da', 'mo_MD', 1],
            'legacy moldavian false' => ['Nu', 'mo_MD', 0],
            'northern sami true' => ['Jo', 'se_NO', 1],
            'northern sami false' => ['Ii', 'se_NO', 0],
            'occitan true' => ['Òc', 'oc_FR', 1],
            'occitan false' => ['Non', 'oc_FR', 0],
            'romansh true' => ['Gea', 'rm_CH', 1],
            'romansh false' => ['Na', 'rm_CH', 0],
            'sardinian true' => ['Eja', 'sc_IT', 1],
            'sardinian false' => ['Nono', 'sc_IT', 0],
            'legacy serbo-croatian true' => ['Da', 'sh_YU', 1],
            'legacy serbo-croatian false' => ['Ne', 'sh_YU', 0],
            'scottish gaelic true' => ['Tha', 'gd_GB', 1],
            'scottish gaelic false' => ['Chan eil', 'gd_GB', 0],
            'welsh true' => ['Ie', 'cy_GB', 1],
            'welsh false' => ['Na', 'cy_GB', 0],
            'yiddish true' => ['יאָ', 'yi_US', 1],
            'yiddish false' => ['קײן', 'yi_US', 0],
            'punctuation is ignored' => [' T-A-K! ', 'pl_PL', 1],
            'wrong language is not guessed' => ['ja', 'pl_PL', null],
            'ordinary label is not boolean' => ['czerwony', 'pl_PL', null],
        ];
    }
}
