<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\Core\Api\PersistedBatchImportRunnerInterface;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttributeConsumer\Api\AttributeSynchronizationBatchInterface;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class AttributeImportProcessTest extends TestCase
{
    public function testResetDelegatesTheOwnedProcessCodeToThePersistedRunner(): void
    {
        $runner = $this->createMock(PersistedBatchImportRunnerInterface::class);
        $runner->expects(self::once())
            ->method('reset')
            ->with(AttributeImportProcess::PROCESS_CODE);

        $this->process($runner, $this->createStub(AttributeSynchronizationBatchInterface::class))->reset();
    }

    public function testBatchUsesCanonicalAutomaticSynchronizationAndAggregatesReviewState(): void
    {
        $batchResult = $this->synchronizationBatch(
            $this->importResult(true, 'next', ['color', 'size']),
            ['created' => 1, 'inserted' => 1, 'conflicts' => 2],
            ['created' => 2, 'linked' => 2, 'errors' => 1],
            3
        );
        $batch = $this->createMock(AttributeSynchronizationBatchInterface::class);
        $batch->expects(self::once())
            ->method('executeAutomatic')
            ->with(null, 50, true)
            ->willReturn($batchResult);
        $runner = $this->createMock(PersistedBatchImportRunnerInterface::class);
        $runner->expects(self::once())
            ->method('executeBatch')
            ->with(AttributeImportProcess::PROCESS_CODE, 200, 50, self::isCallable())
            ->willReturnCallback(
                static function (string $code, int $default, ?int $pageSize, callable $import): array {
                    return $import(null, $pageSize ?? $default);
                }
            );

        $result = $this->process($runner, $batch)->executeBatch(50);

        self::assertSame(1, $result['created_attributes']);
        self::assertSame(1, $result['auto_mapped']);
        self::assertSame(2, $result['mapping_conflicts']);
        self::assertSame(2, $result['option_mappings']);
        self::assertSame(1, $result['options']['errors']);
        self::assertSame(3, $result['review_required']);
    }

    public function testFullImportSynchronizesEveryBatchThroughTheSameCoordinator(): void
    {
        $batch = $this->createMock(AttributeSynchronizationBatchInterface::class);
        $batch->expects(self::exactly(2))
            ->method('executeAutomatic')
            ->willReturnOnConsecutiveCalls(
                $this->synchronizationBatch(
                    $this->importResult(true, 'next', ['color']),
                    ['created' => 1, 'inserted' => 1],
                    ['linked' => 1],
                    0
                ),
                $this->synchronizationBatch(
                    $this->importResult(false, 'terminal', ['size']),
                    ['created' => 1, 'inserted' => 1],
                    ['linked' => 1, 'skipped' => 1],
                    1
                )
            );
        $runner = $this->createMock(PersistedBatchImportRunnerInterface::class);
        $runner->expects(self::once())
            ->method('executeUntilComplete')
            ->with(AttributeImportProcess::PROCESS_CODE, 200, 100, 5, self::isCallable())
            ->willReturnCallback(
                static function (
                    string $code,
                    int $default,
                    ?int $pageSize,
                    int $maxBatches,
                    callable $import
                ): array {
                    $first = $import(null, $pageSize ?? $default);
                    $second = $import((string)$first['cursor'], $pageSize ?? $default);

                    return [
                        'batches' => 2,
                        'imported' => $first['imported'] + $second['imported'],
                        'changed' => $first['changed'] + $second['changed'],
                        'unchanged' => $first['unchanged'] + $second['unchanged'],
                        'has_more' => $second['has_more'],
                    ];
                }
            );

        $result = $this->process($runner, $batch)->executeUntilComplete(100, 5);

        self::assertSame(2, $result['created_attributes']);
        self::assertSame(2, $result['auto_mapped']);
        self::assertSame(2, $result['option_mappings']);
        self::assertSame(2, $result['options']['linked']);
        self::assertSame(1, $result['review_required']);
    }

    public function testIncompleteBatchIsRejectedBeforeRunnerCanPersistCursor(): void
    {
        $batchResult = $this->synchronizationBatch(
            $this->importResult(true, 'next', ['color']),
            [],
            [],
            0
        );
        $batchResult['completion']['cursor_advance_allowed'] = false;
        $batch = $this->createStub(AttributeSynchronizationBatchInterface::class);
        $batch->method('executeAutomatic')->willReturn($batchResult);
        $runner = $this->createMock(PersistedBatchImportRunnerInterface::class);
        $runner->expects(self::once())->method('executeBatch')->willReturnCallback(
            static function (string $code, int $default, ?int $pageSize, callable $import): array {
                return $import(null, $pageSize ?? $default);
            }
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cursor was preserved');

        $this->process($runner, $batch)->executeBatch();
    }

    /**
     * @param string[] $attributeCodes
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     attribute_codes: string[]
     * }
     */
    private function importResult(bool $hasMore, ?string $cursor, array $attributeCodes): array
    {
        return [
            'has_more' => $hasMore,
            'cursor' => $cursor,
            'page_size' => 100,
            'imported' => 1,
            'changed' => 1,
            'unchanged' => 0,
            'attribute_codes' => $attributeCodes,
        ];
    }

    /**
     * @param array<string, int> $mapping
     * @param array<string, int> $optionOverrides
     * @return array<string, mixed>
     */
    private function synchronizationBatch(
        array $import,
        array $mapping,
        array $optionOverrides,
        int $reviewRequired
    ): array {
        return [
            'import' => $import,
            'mapping' => array_replace($this->mappingStats(), $mapping),
            'options' => [
                'mappings' => array_fill(0, (int)($optionOverrides['linked'] ?? 0), ['mapping_id' => 1]),
                'summary' => array_replace($this->optionStats(), $optionOverrides),
            ],
            'completion' => [
                'cursor_advance_allowed' => true,
                'review_required' => $reviewRequired,
            ],
        ];
    }

    /** @return array<string, int> */
    private function mappingStats(): array
    {
        return [
            'matched' => 0,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
        ];
    }

    /** @return array<string, int> */
    private function optionStats(): array
    {
        return [
            'created' => 0,
            'linked' => 0,
            'mappings_inserted' => 0,
            'mappings_updated' => 0,
            'labels_updated' => 0,
            'sort_order_updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    private function process(
        PersistedBatchImportRunnerInterface $runner,
        AttributeSynchronizationBatchInterface $batch
    ): AttributeImportProcess {

        return new AttributeImportProcess($runner, $batch, new ChangeReport(new Json()));
    }
}
