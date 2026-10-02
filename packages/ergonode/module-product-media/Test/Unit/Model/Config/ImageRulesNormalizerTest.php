<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Config;

use Ergonode\ProductMedia\Model\Config\ImageRulesNormalizer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageRulesNormalizerTest extends TestCase
{
    public function testClearedFormAndNumericNormalization(): void
    {
        $normalizer = new ImageRulesNormalizer();
        self::assertSame([], $normalizer->normalize(['__empty' => []]));
        self::assertSame([['attribute' => 'back', 'position' => 2]], $normalizer->normalize([
            'row' => ['attribute' => ' back ', 'position' => '2'],
        ]));
    }
    /** @return list<array{array<int,array{attribute:string,position:int|string}>}> */
    public static function invalidRules(): array
    {
        return [
            [[['attribute' => 'back', 'position' => 1]]],
            [[['attribute' => 'back', 'position' => '2.5']]],
            [[['attribute' => 'back', 'position' => 2], ['attribute' => 'detail', 'position' => 2]]],
            [[['attribute' => 'back', 'position' => 2], ['attribute' => 'back', 'position' => 3]]],
        ];
    }
    #[DataProvider('invalidRules')]
    public function testRejectsAmbiguousRules(array $rows): void
    {
        $this->expectException(LocalizedException::class);
        (new ImageRulesNormalizer())->normalize($rows);
    }
}
