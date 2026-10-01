<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryNameNormalizerTest extends TestCase
{
    #[DataProvider('whitespaceProvider')]
    public function testNormalizesEveryWhitespaceRun(string $input, string $expected): void
    {
        self::assertSame($expected, (new CategoryNameNormalizer())->normalize($input));
    }

    /** @return array<string, array{string, string}> */
    public static function whitespaceProvider(): array
    {
        return [
            'one space' => ['Krzesła biurowe', 'krzesła biurowe'],
            'two spaces' => ['Krzesła  biurowe', 'krzesła biurowe'],
            'three spaces' => ['Krzesła   biurowe', 'krzesła biurowe'],
            'many spaces' => ['Krzesła       biurowe', 'krzesła biurowe'],
            'tabs and lines' => ["\tKrzesła\n\r biurowe\t", 'krzesła biurowe'],
            'unicode separators' => ["\u{00A0}Krzesła\u{2003}\u{202F}biurowe\u{00A0}", 'krzesła biurowe'],
            'trim after reduction' => ['   Krzesła   ', 'krzesła'],
            'punctuation and accents' => ['ŁÓDŹ, Śląsk!', 'łódź, śląsk!'],
            'empty' => [" \t\n\u{00A0}", ''],
        ];
    }
}
