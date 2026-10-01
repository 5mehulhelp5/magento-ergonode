<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Model;

use Ergonode\ProductAttributeHistory\Model\Config\HistoryConfig;
use Ergonode\ProductAttributeHistory\Model\HistoryCleanup;
use Ergonode\ProductAttributeHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HistoryCleanupTest extends TestCase
{
    public function testCleanupUsesThirtyDaysAndRunsWithRecordingDisabled(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::once())->method('isSetFlag')->with('ergonode_products/history/cleanup_enabled')
            ->willReturn(true);
        $scope->expects(self::once())->method('getValue')->with('ergonode_products/history/retention_days')
            ->willReturn('30');
        $pruner = $this->createMock(HistoryPrunerInterface::class);
        $pruner->expects(self::once())->method('deleteBefore')->with('2026-08-12 00:00:00')->willReturn(2);
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1789084800);
        $clock->method('gmtDate')->willReturnCallback(
            static fn (string $format, int $time): string => gmdate($format, $time)
        );

        (new HistoryCleanup(new HistoryConfig($scope), $pruner, $clock))->execute();
    }

    #[DataProvider('skippedCleanup')]
    public function testDisabledCleanupAndInvalidRetentionNeverDelete(bool $enabled, string $days): void
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturn($enabled);
        $scope->method('getValue')->willReturn($days);
        $pruner = $this->createMock(HistoryPrunerInterface::class);
        $pruner->expects(self::never())->method('deleteBefore');

        (new HistoryCleanup(new HistoryConfig($scope), $pruner, $this->createStub(DateTime::class)))->execute();
    }

    /** @return array<string, array{bool, string}> */
    public static function skippedCleanup(): array
    {
        return [
            'disabled' => [false, '30'],
            'zero' => [true, '0'],
            'negative' => [true, '-1'],
            'fraction' => [true, '1.5'],
            'empty' => [true, ''],
            'invalid' => [true, 'abc'],
        ];
    }
}
