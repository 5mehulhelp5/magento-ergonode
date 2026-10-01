<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Model\Mapping\OptionLabelKeyNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OptionLabelKeyNormalizerTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function labelProvider(): array
    {
        return [
            'case and whitespace' => [' Modern Style ', 'modernstyle'],
            'diacritics and punctuation' => ['Żółte krzesła!', 'zoltekrzesla'],
            'empty' => [' -- ', ''],
        ];
    }

    #[DataProvider('labelProvider')]
    public function testNormalizesLabelToStableMappingKey(string $label, string $expected): void
    {
        self::assertSame($expected, (new OptionLabelKeyNormalizer())->normalize($label));
    }
}
