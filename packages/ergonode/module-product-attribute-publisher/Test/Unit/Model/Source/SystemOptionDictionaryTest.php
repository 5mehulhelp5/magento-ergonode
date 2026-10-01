<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Test\Unit\Model\Source;

use Ergonode\ProductAttributePublisher\Model\Source\SystemOptionDictionary;
use PHPUnit\Framework\TestCase;

class SystemOptionDictionaryTest extends TestCase
{
    public function testLanguageRegionScriptAndEnglishFallback(): void
    {
        $dictionary = new SystemOptionDictionary();
        self::assertSame('Tak', $dictionary->translate(0, 'pl_PL'));
        self::assertSame('Ja', $dictionary->translate(0, 'de-AT'));
        self::assertSame('Nee', $dictionary->translate(1, 'nl_BE'));
        self::assertSame('Nei', $dictionary->translate(1, 'nb_NO'));
        self::assertSame('Претрага', $dictionary->translate(4, 'sr_Cyrl_RS'));
        self::assertSame('Pretraga', $dictionary->translate(4, 'sr-Latn-RS'));
        self::assertSame('Pretraga', $dictionary->translate(4, 'cnr_ME'));
        self::assertSame('Претрага', $dictionary->translate(4, 'cnr_Cyrl_ME'));
        self::assertSame('No', $dictionary->translate(1, 'zh_CN'));
        self::assertSame('Catalog, Search', $dictionary->translate(5, 'unknown'));
    }
}
