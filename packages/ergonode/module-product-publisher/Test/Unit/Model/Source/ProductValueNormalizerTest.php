<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use Ergonode\ProductPublisher\Model\Source\ProductValueNormalizer;
use Ergonode\ProductPublisher\Model\Exception\MissingOptionMappingException;
use PHPUnit\Framework\TestCase;

class ProductValueNormalizerTest extends TestCase
{
    public function testMapsMagentoOptionIdsToStableErgonodeCodes(): void
    {
        $normalizer = new ProductValueNormalizer();

        self::assertSame('red', $normalizer->normalize(7, 'select', ['red' => 7], 'color'));
        self::assertSame(
            ['blue', 'red'],
            $normalizer->normalize('7,9', 'multi_select', ['red' => 7, 'blue' => 9], 'colors')
        );
    }

    public function testRejectsUnmappedMagentoOption(): void
    {
        $this->expectException(MissingOptionMappingException::class);

        (new ProductValueNormalizer())->normalize(8, 'select', ['red' => 7], 'color');
    }

    public function testMultiSelectReportsEveryMissingOptionWithoutPartialValue(): void
    {
        try {
            (new ProductValueNormalizer())->normalize(
                '7,7788,1,7788',
                'multi_select',
                ['red' => 7],
                'product "T-2105" attribute "product_type_pim"'
            );
            self::fail('Expected incomplete option mapping.');
        } catch (MissingOptionMappingException $exception) {
            self::assertSame(['1', '7788'], $exception->getOptionIds());
        }
    }

    public function testNormalizesNumbersListsAndEmptyValues(): void
    {
        $normalizer = new ProductValueNormalizer();

        self::assertSame(12.5, $normalizer->normalize('12.5', 'price', [], 'price'));
        self::assertSame(['A', 'B'], $normalizer->normalize('B,A,B', 'product_relation', [], 'related'));
        self::assertNull($normalizer->normalize('', 'text', [], 'description'));
    }
}
