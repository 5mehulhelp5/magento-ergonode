<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Plugin;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryRecorder;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;
use Ergonode\CategoryConsumerHistory\Model\Operation\OperationContext;
use Ergonode\CategoryConsumerHistory\Plugin\CategoryEntitySynchronizationHistoryPlugin;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class CategoryEntitySynchronizationHistoryPluginTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testCapturesOnlyWrittenTargetsAndPreservesResultOrPartialFailure(bool $fails): void
    {
        $operations = [['category_id' => 12, 'entity' => ['code' => 'chairs']]];
        $before = ['target' => [['identifier' => '12', 'label' => 'Old']]];
        $after = ['target' => [['identifier' => '12', 'label' => 'New']]];
        $provider = $this->createMock(CategoryTreeStateProviderInterface::class);
        $provider->expects(self::exactly(2))->method('getState')->with(7)
            ->willReturnOnConsecutiveCalls($before, $after);
        $recorder = $this->createMock(CategoryTreeHistoryRecorder::class);
        $recorder->expects(self::once())->method('record')->with(
            'synchronize_data',
            $fails ? 'failed' : 'success',
            '2026-09-18 12:00:00',
            $fails ? ['failed' => 1] : ['attributes' => 1, 'trees' => 1],
            [7 => $before],
            [7 => $after]
        );
        $context = new OperationContext();
        $config = $this->createStub(HistoryConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $date = $this->createStub(DateTime::class);
        $date->method('gmtDate')->willReturn('2026-09-18 12:00:00');
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $query = $this->createMock(CategoryMappingQuery::class);
        $query->expects(self::once())->method('getValidMappingsByCodes')->with(['chairs'])->willReturn([
            'chairs' => [
                ['category_tree_id' => 7, 'magento_category_id' => 12],
                ['category_tree_id' => 8, 'magento_category_id' => 99],
            ],
        ]);
        $plugin = new CategoryEntitySynchronizationHistoryPlugin($query, new CategoryTreeHistoryCapture(
            $provider,
            $recorder,
            $context,
            $date,
            new NullLogger(),
            $config,
            new CategorySynchronizationLock($lockManager)
        ), $context, $config);
        $stats = ['snapshots' => 0, 'snapshot_statuses' => [], 'attributes' => 1, 'changed_category_ids' => [12]];
        $error = new RuntimeException('Partial name write');
        $calls = 0;
        try {
            $result = $plugin->aroundSynchronize(
                $this->createStub(CategoryEntitySynchronizerInterface::class),
                static function (array $input) use ($operations, $stats, $fails, $error, &$calls): array {
                    self::assertSame($operations, $input);
                    $calls++;
                    if ($fails) {
                        throw $error;
                    }
                    return $stats;
                },
                $operations
            );
            self::assertFalse($fails);
            self::assertSame($stats, $result);
        } catch (RuntimeException $caught) {
            self::assertTrue($fails);
            self::assertSame($error, $caught);
        }
        self::assertSame(1, $calls);
        self::assertFalse($context->isActive());
    }

    public function testEmptyDisabledAndNestedBatchesDoNotReadTreesOrCaptureAgain(): void
    {
        foreach (['empty', 'disabled', 'nested'] as $case) {
            $context = new OperationContext();
            if ($case === 'nested') {
                $context->enter();
            }
            $config = $this->createStub(HistoryConfig::class);
            $config->method('isEnabled')->willReturn($case !== 'disabled');
            $query = $this->createMock(CategoryMappingQuery::class);
            $query->expects(self::never())->method('getValidMappingsByCodes');
            $capture = $this->createMock(CategoryTreeHistoryCapture::class);
            $capture->expects(self::never())->method('execute');
            $operations = $case === 'empty' ? [] : [['category_id' => 12, 'entity' => ['code' => 'chairs']]];
            $calls = 0;
            $result = (new CategoryEntitySynchronizationHistoryPlugin($query, $capture, $context, $config))
                ->aroundSynchronize(
                    $this->createStub(CategoryEntitySynchronizerInterface::class),
                    static function (array $input) use ($operations, &$calls): array {
                        self::assertSame($operations, $input);
                        $calls++;
                        return ['attributes' => 0];
                    },
                    $operations
                );
            self::assertSame(['attributes' => 0], $result);
            self::assertSame(1, $calls);
        }
    }

    public static function outcomes(): array
    {
        return ['success' => [false], 'partial failure' => [true]];
    }
}
