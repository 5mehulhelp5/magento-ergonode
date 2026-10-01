<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Model\Operation;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryRecorder;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;
use Ergonode\CategoryConsumerHistory\Model\Operation\OperationContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CategoryTreeHistoryCaptureTest extends TestCase
{
    public function testCapturesAllTreesAroundOneOperation(): void
    {
        $stateProvider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $stateProvider->expects(self::exactly(4))
            ->method('getState')
            ->willReturnCallback(static fn (int $treeId): array => ['tree' => ['category_tree_id' => $treeId]]);
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::once())
            ->method('record')
            ->with(
                'synchronize',
                'success',
                '2026-09-06 10:00:00',
                ['trees' => 2],
                [1 => ['tree' => ['category_tree_id' => 1]], 2 => ['tree' => ['category_tree_id' => 2]]],
                [1 => ['tree' => ['category_tree_id' => 1]], 2 => ['tree' => ['category_tree_id' => 2]]]
            );
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-09-06 10:00:00');
        $operationContext = new OperationContext();
        $capture = new CategoryTreeHistoryCapture(
            $stateProvider,
            $recorder,
            $operationContext,
            $dateTime,
            $this->createStub(LoggerInterface::class),
            $this->historyConfig(),
            $this->synchronizationLock()
        );

        $result = $capture->execute(
            'synchronize',
            [1, 2],
            static fn (): array => ['trees' => 2],
            static fn (array $summary): array => ['status' => 'success', 'summary' => $summary]
        );

        self::assertSame(['trees' => 2], $result);
        self::assertFalse($operationContext->isActive());
    }

    public function testNestedMutationIsNotRecordedSeparately(): void
    {
        $stateProvider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $stateProvider->expects(self::never())->method('getState');
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::never())->method('record');
        $context = new OperationContext();
        $context->enter();
        $capture = new CategoryTreeHistoryCapture(
            $stateProvider,
            $recorder,
            $context,
            $this->clock(),
            $this->createStub(LoggerInterface::class),
            $this->historyConfig(),
            $this->synchronizationLock()
        );

        $result = $capture->execute(
            'refresh_snapshot',
            [1],
            static fn (): string => 'nested result',
            static fn (): array => ['status' => 'success', 'summary' => []]
        );

        self::assertSame('nested result', $result);
        $context->leave();
    }
    public function testDisabledHistorySkipsStatesAndPreservesOperationOutcome(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::exactly(2))->method('isSetFlag')
            ->with('ergonode_categories/history/enabled')->willReturn(false);
        $stateProvider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $stateProvider->expects(self::never())->method('getState');
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::never())->method('record');
        $context = new OperationContext();
        $capture = new CategoryTreeHistoryCapture(
            $stateProvider,
            $recorder,
            $context,
            $this->clock(),
            $this->createStub(LoggerInterface::class),
            new HistoryConfig($scope),
            $this->synchronizationLock()
        );
        $describe = static function (): array {
            self::fail('Disabled history must not describe the result.');
        };
        self::assertSame(7, $capture->execute('save', [1], static fn (): int => 7, $describe));
        $failure = new RuntimeException('original');
        try {
            $capture->execute('save', [1], static fn () => throw $failure, $describe);
            self::fail('Expected original exception.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertFalse($context->isActive());
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

    public function testGroupedRunCapturesOnlyTouchedTreesAndKeepsTheLockUntilHistoryIsWritten(): void
    {
        $locked = false;
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())->method('lock')->willReturnCallback(
            static function () use (&$locked): bool {
                $locked = true;
                return true;
            }
        );
        $manager->expects(self::once())->method('unlock')->willReturnCallback(
            static function () use (&$locked): bool {
                $locked = false;
                return true;
            }
        );
        $states = $this->createMock(CategoryTreeStateProviderInterface::class);
        $states->expects(self::never())->method('getTrees');
        $states->expects(self::exactly(4))->method('getState')->willReturnCallback(
            static function (int $id) use (&$locked): array {
                self::assertTrue($locked);
                return ['tree' => ['category_tree_id' => $id]];
            }
        );
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::once())->method('begin')->willReturn(99);
        $recorder->expects(self::never())->method('record');
        $recorder->expects(self::exactly(2))->method('append')->willReturnCallback(
            static function (int $id, array $before, array $after) use (&$locked): void {
                self::assertTrue($locked);
                self::assertSame(99, $id);
                self::assertCount(1, $before);
                self::assertCount(1, $after);
            }
        );
        $recorder->expects(self::once())->method('finish')->with(99, 'success', ['trees' => 2]);
        $context = new OperationContext();
        $capture = new CategoryTreeHistoryCapture(
            $states,
            $recorder,
            $context,
            $this->clock(),
            $this->createStub(LoggerInterface::class),
            $this->historyConfig(),
            new CategorySynchronizationLock($manager)
        );
        $describe = static fn (): array => ['status' => 'success', 'summary' => ['trees' => 2]];
        $capture->executeGrouped('synchronize', function () use ($capture, $describe): void {
            foreach ([7, 9] as $id) {
                $capture->execute('reconcile', [$id], function () use ($capture, $id, $describe): void {
                    $capture->execute('refresh_snapshot', [$id], static fn (): int => 1, $describe);
                }, $describe);
            }
        }, $describe);
        self::assertFalse($locked);
        self::assertFalse($context->isActive());
        self::assertFalse($context->isGrouped());
    }

    public function testGroupedNoOpDoesNotCaptureAnyTree(): void
    {
        $states = $this->createMock(CategoryTreeStateProviderInterface::class);
        $states->expects(self::never())->method('getState');
        $states->expects(self::never())->method('getTrees');
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->method('begin')->willReturn(1);
        $recorder->expects(self::never())->method('append');
        $recorder->expects(self::once())->method('finish')->with(1, 'success', ['trees' => 0]);
        $capture = new CategoryTreeHistoryCapture(
            $states,
            $recorder,
            new OperationContext(),
            $this->clock(),
            $this->createStub(LoggerInterface::class),
            $this->historyConfig(),
            $this->synchronizationLock()
        );
        $capture->executeGrouped(
            'synchronize',
            static fn (): int => 0,
            static fn (): array => ['status' => 'success', 'summary' => ['trees' => 0]]
        );
    }
    private function clock(): DateTime
    {
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtDate')->willReturn('2026-09-13 10:00:00');
        return $clock;
    }
}
