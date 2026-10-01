<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model;

use Ergonode\Language\Model\LanguageCodeNormalizer;
use PHPUnit\Framework\TestCase;

class LanguageCodeNormalizerTest extends TestCase
{
    public function testNormalizesLanguageAndLocaleCodesForComparison(): void
    {
        $normalizer = new LanguageCodeNormalizer();

        self::assertSame('pl_pl', $normalizer->normalize(' PL-PL '));
        self::assertSame('zh_hant_tw', $normalizer->normalize('zh-Hant-TW'));
    }
}
