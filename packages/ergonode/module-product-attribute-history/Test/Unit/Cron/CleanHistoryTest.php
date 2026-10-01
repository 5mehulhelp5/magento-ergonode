<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Cron;

use Ergonode\ProductAttributeHistory\Cron\CleanHistory;
use Ergonode\ProductAttributeHistory\Model\Config\HistoryConfig;
use Ergonode\ProductAttributeHistory\Model\HistoryCleanup;
use Ergonode\ProductAttributeHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CleanHistoryTest extends TestCase
{
    public function testCronUsesRetentionWithoutReadingConnectionConfiguration(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::once())->method('isSetFlag')->with('ergonode_products/history/cleanup_enabled')
            ->willReturn(true);
        $scope->expects(self::once())->method('getValue')->with('ergonode_products/history/retention_days')
            ->willReturn('30');
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1789084800);
        $clock->method('gmtDate')
            ->willReturnCallback(static fn (string $format, int $time): string => gmdate($format, $time));
        $pruner = $this->createMock(HistoryPrunerInterface::class);
        $pruner->expects(self::once())->method('deleteBefore')->with('2026-08-12 00:00:00')->willReturn(0);
        (new CleanHistory(new HistoryCleanup(new HistoryConfig($scope), $pruner, $clock)))->execute();
    }

    public function testDatabaseFailureIsNotMistakenForMissingConnection(): void
    {
        $config = $this->createStub(HistoryConfig::class);
        $config->method('isCleanupEnabled')->willReturn(true);
        $config->method('getRetentionDays')->willReturn(30);
        $pruner = $this->createStub(HistoryPrunerInterface::class);
        $pruner->method('deleteBefore')->willThrowException(new RuntimeException('Database failed'));
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1789084800);
        $clock->method('gmtDate')->willReturn('2026-08-12 00:00:00');
        $this->expectException(RuntimeException::class);
        (new CleanHistory(new HistoryCleanup($config, $pruner, $clock)))->execute();
    }
}
