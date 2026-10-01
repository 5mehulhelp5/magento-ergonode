<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Unit\Model;

use Ergonode\ProductAttributeHistory\Model\Context\ExecutionContext;
use Ergonode\ProductAttributeHistory\Model\OperationCapture;
use Ergonode\ProductAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Ergonode\ProductAttributeHistory\Model\SnapshotProvider;
use Ergonode\ProductAttributeHistory\Model\StateDiffer;
use Ergonode\ProductAttributeHistory\Model\Config\HistoryConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class OperationCaptureTest extends TestCase
{
    public function testNestedOperationProducesOneSnapshotPairAndKeepsResult(): void
    {
        $provider = $this->createMock(SnapshotProvider::class);
        $provider->expects(self::exactly(2))->method('getState')->willReturn(['source' => [], 'target' => []]);
        $writer = $this->createMock(HistoryWriterInterface::class);
        $writer->expects(self::once())->method('save')->with(self::callback(static function (array $row): bool {
            return $row['operation_code'] === 'synchronize'
                && $row['status'] === 'success' && $row['change_count'] === 0;
        }));
        $capture = $this->capture($provider, $writer);

        self::assertSame(['saved' => 1], $capture->execute(
            'synchronize',
            static fn (): array => $capture->execute('save', static fn (): array => ['saved' => 1])
        ));
    }

    public function testFailedOperationRecordsPartialStateAndRethrowsOriginalException(): void
    {
        $provider = $this->createMock(SnapshotProvider::class);
        $attribute = [
            'code' => 'new', 'label' => 'New', 'type' => 'text',
            'scope' => 'global', 'active' => true, 'mapped_code' => null,
        ];
        $provider->expects(self::exactly(2))->method('getState')->willReturnOnConsecutiveCalls(
            ['source' => [], 'target' => []],
            ['source' => [$attribute], 'target' => []]
        );
        $writer = $this->createMock(HistoryWriterInterface::class);
        $writer->expects(self::once())->method('save')->with(self::callback(static function (array $row): bool {
            return $row['status'] === 'failed'
                && $row['change_count'] === 1 && $row['changes'][0]['actions'] === ['created'];
        }));
        $capture = $this->capture($provider, $writer);
        $failure = new RuntimeException('Original failure');
        try {
            $capture->execute('save', static fn () => throw $failure);
            self::fail('The original exception must escape.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testUnavailableHistoryDoesNotBlockOperationsOrKeepNestedFlagSet(): void
    {
        $provider = $this->createStub(SnapshotProvider::class);
        $provider->method('getState')->willReturn(['source' => [], 'target' => []]);
        $writer = $this->createMock(HistoryWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')
            ->willThrowException(new RuntimeException('History unavailable'));
        $capture = $this->capture($provider, $writer);

        self::assertSame(1, $capture->execute('save', static fn (): int => 1));
        self::assertSame(2, $capture->execute('save', static fn (): int => 2));
    }

    public function testDisabledHistoryPreservesResultsAndExceptionsWithoutSnapshots(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::exactly(3))->method('isSetFlag')
            ->with('ergonode_products/history/enabled')->willReturn(false);
        $snapshot = $this->createMock(SnapshotProvider::class);
        $snapshot->expects(self::never())->method('getState');
        $writer = $this->createMock(HistoryWriterInterface::class);
        $writer->expects(self::never())->method('save');
        $capture = new OperationCapture(
            $snapshot,
            new StateDiffer(),
            $writer,
            $this->createStub(ExecutionContext::class),
            $this->createStub(LoggerInterface::class),
            new HistoryConfig($scope)
        );
        self::assertSame(7, $capture->execute(
            'save',
            static fn (): int => $capture->execute('save', static fn (): int => 7)
        ));
        $failure = new RuntimeException('original');
        try {
            $capture->execute('save', static fn () => throw $failure);
            self::fail('Expected original exception.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    private function capture(SnapshotProvider $provider, HistoryWriterInterface $writer): OperationCapture
    {
        $context = $this->createStub(ExecutionContext::class);
        $context->method('get')->willReturn(['origin' => 'cli', 'actor_id' => null, 'actor_name' => null]);

        return new OperationCapture(
            $provider,
            new StateDiffer(),
            $writer,
            $context,
            $this->createStub(LoggerInterface::class),
            $this->historyConfig()
        );
    }
    private function historyConfig(): HistoryConfig
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturn(true);

        return new HistoryConfig($scope);
    }
}
