<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Plugin;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryRecorder;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;
use Ergonode\CategoryConsumerHistory\Model\Operation\OperationContext;
use Ergonode\CategoryConsumerHistory\Plugin\CategorySnapshotRemoverHistoryPlugin;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CategorySnapshotRemoverHistoryPluginTest extends TestCase
{
    #[DataProvider('removalOutcomes')]
    public function testCapturesRemovalAndPreservesItsOutcome(bool $fails): void
    {
        $before = ['source' => [['identifier' => 'bottoms']], 'target' => []];
        $after = $fails ? $before : ['source' => [], 'target' => []];
        $stateProvider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $stateProvider->expects(self::exactly(2))->method('getState')->with(7)
            ->willReturnOnConsecutiveCalls($before, $after);
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::once())->method('record')->with(
            'remove_snapshot',
            $fails ? 'failed' : 'success',
            '2026-09-07 12:00:00',
            $fails ? ['failed' => 1] : ['removed' => 1],
            [7 => $before],
            [7 => $after]
        );
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-09-07 12:00:00');
        $context = new OperationContext();
        $plugin = new CategorySnapshotRemoverHistoryPlugin(new CategoryTreeHistoryCapture(
            $stateProvider,
            $recorder,
            $context,
            $dateTime,
            new NullLogger(),
            $this->historyConfig(),
            $this->synchronizationLock()
        ));
        $calls = 0;
        $exception = new LocalizedException(__('Category is missing.'));

        try {
            $plugin->aroundRemove(
                $this->createStub(CategorySnapshotRemoverInterface::class),
                static function (int $treeId, string $code) use (&$calls, $fails, $exception): void {
                    $calls++;
                    self::assertSame(7, $treeId);
                    self::assertSame(' bottoms ', $code);
                    if ($fails) {
                        throw $exception;
                    }
                },
                7,
                ' bottoms '
            );
            self::assertFalse($fails);
        } catch (LocalizedException $caught) {
            self::assertTrue($fails);
            self::assertSame($exception, $caught);
        }
        self::assertSame(1, $calls);
        self::assertFalse($context->isActive());
    }

    /** @return array<string, array{bool}> */
    public static function removalOutcomes(): array
    {
        return ['success' => [false], 'failure' => [true]];
    }
    private function historyConfig(): HistoryConfig
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturn(true);

        return new HistoryConfig($scope);
    }
    private function synchronizationLock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new CategorySynchronizationLock($manager);
    }
}
