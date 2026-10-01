<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\TreePageSizePolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TreePageSizePolicyTest extends TestCase
{
    #[DataProvider('configurationValues')]
    public function testUsesConfiguredInitialSizeOrSafeDefault(mixed $value, int $expected): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->expects(self::once())->method('getValue')
            ->with('ergonode_categories/tree/initial_page_size')->willReturn($value);

        self::assertSame($expected, (new TreePageSizePolicy($config))->initialSize());
    }

    public static function configurationValues(): array
    {
        return [[null, 700], ['', 700], ['700', 700], ['750', 750], ['100', 100], ['1000', 1000],
            ['99', 700], ['1001', 700], ['750.5', 700], ['invalid', 700]];
    }

    #[DataProvider('responseTimes')]
    public function testAdjustsOnlyFullPagesWithinBounds(int $current, int $count, float $seconds, int $next): void
    {
        $policy = new TreePageSizePolicy($this->createStub(ScopeConfigInterface::class));

        self::assertSame($next, $policy->nextSize($current, $count, $seconds));
    }

    public static function responseTimes(): array
    {
        return [
            [700, 700, 1.999, 800], [700, 700, 2.0, 700], [700, 700, 3.0, 700],
            [700, 700, 4.0, 700], [700, 700, 4.001, 600], [1000, 1000, 1.0, 1000],
            [950, 950, 1.0, 1000], [150, 150, 6.0, 100], [100, 100, 6.0, 100],
            [700, 71, 0.2, 700], [700, 71, 6.0, 700], [700, 0, 0.1, 700],
            [50, 50, 6.0, 50], [25, 25, 3.0, 25], [50, 50, 1.0, 150],
        ];
    }

    public function testRetrySizesKeepExactConfiguredValueAndDecreaseWithoutRepeating(): void
    {
        $policy = new TreePageSizePolicy($this->createStub(ScopeConfigInterface::class));

        self::assertSame([750, 500, 200, 100, 50, 25], $policy->retrySizes(750));
        self::assertSame([500, 200, 100, 50, 25], $policy->retrySizes(500));
        self::assertSame([100, 50, 25], $policy->retrySizes(100));
        self::assertSame([25], $policy->retrySizes(25));
    }
}
