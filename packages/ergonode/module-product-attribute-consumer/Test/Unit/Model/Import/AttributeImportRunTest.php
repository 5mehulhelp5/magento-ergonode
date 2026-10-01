<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeBatchImporterInterface;
use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Import\PersistedBatchImportRunner;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Ergonode\ProductAttributeConsumer\Model\Sync\AttributeSynchronizationBatch;
use Ergonode\ProductAttributeConsumer\Model\Sync\AttributeSynchronizationOutcomePolicy;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AttributeImportRunTest extends TestCase
{
    public function testTwentyPagesReconcileOnceAndTheNextInvocationReconcilesAgain(): void
    {
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $definitions->expects(self::exactly(2))->method('synchronize')->with(false);
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $page = 0;
        $importer->expects(self::exactly(21))->method('import')->willReturnCallback(
            function (?string $cursor, ?int $pageSize) use (&$page): array {
                self::assertSame('remote-' . $page, $cursor);
                self::assertSame(25, $pageSize);
                $page++;
                return $this->page($page < 20, 'remote-' . $page);
            }
        );
        $savedCursor = 'remote-0';
        $process = $this->process($definitions, $importer, $savedCursor);

        $result = $process->executeUntilComplete(25);
        self::assertSame(20, $result['batches']);
        self::assertFalse($result['has_more']);
        self::assertSame('remote-20', $savedCursor);

        $process->executeUntilComplete(25);
        self::assertSame('remote-21', $savedCursor);
    }

    public function testStandaloneBatchesAlwaysReconcileAndEmptyTerminalPagePreservesCursor(): void
    {
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $forced = [];
        $definitions->expects(self::exactly(2))->method('synchronize')->willReturnCallback(
            static function (bool $force) use (&$forced): void {
                $forced[] = $force;
            }
        );
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $importer->expects(self::exactly(2))->method('import')->willReturnOnConsecutiveCalls(
            $this->page(false, 'terminal'),
            $this->page(false, null)
        );
        $savedCursor = null;
        $process = $this->process($definitions, $importer, $savedCursor);

        $process->executeBatch();
        $process->executeBatch();

        self::assertSame([false, false], $forced);
        self::assertSame('terminal', $savedCursor);
    }

    public function testBatchLimitStartsANewReconciliationWhenResumed(): void
    {
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $definitions->expects(self::exactly(2))->method('synchronize')->with(false);
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $importer->expects(self::exactly(2))->method('import')->willReturnOnConsecutiveCalls(
            $this->page(true, 'next'),
            $this->page(false, 'terminal')
        );
        $savedCursor = 'existing';
        $process = $this->process($definitions, $importer, $savedCursor);

        self::assertTrue($process->executeUntilComplete(25, 1)['has_more']);
        self::assertSame('next', $savedCursor);
        self::assertFalse($process->executeUntilComplete(25, 1)['has_more']);
        self::assertSame('terminal', $savedCursor);
    }

    public function testResetRestartsRemoteImportWithoutForcingSharedSnapshotDownload(): void
    {
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $definitions->expects(self::once())->method('synchronize')->with(false);
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $importer->expects(self::once())->method('import')->with(null, 25)
            ->willReturn($this->page(false, 'terminal'));
        $savedCursor = 'previous-import';
        $process = $this->process($definitions, $importer, $savedCursor);

        $process->reset();
        self::assertNull($savedCursor);
        $process->executeUntilComplete(25);

        self::assertSame('terminal', $savedCursor);
    }

    #[DataProvider('failureStages')]
    public function testFailurePreservesCursorAndRetryReconcilesAgain(string $stage): void
    {
        $failure = new RuntimeException('Failed ' . $stage);
        $definitions = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $checks = 0;
        $definitions->expects(self::exactly(2))->method('synchronize')->with(false)->willReturnCallback(
            static function () use ($stage, $failure, &$checks): void {
                $checks++;
                if ($stage === 'definitions' && $checks === 1) {
                    throw $failure;
                }
            }
        );
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $calls = 0;
        $importer->expects(self::exactly($stage === 'definitions' ? 1 : 3))->method('import')
            ->willReturnCallback(function (?string $cursor) use ($stage, $failure, &$calls): array {
                $calls++;
                if ($stage === 'definitions') {
                    self::assertSame('existing', $cursor);
                    return $this->page(false, 'terminal');
                }
                self::assertSame($calls === 1 ? 'existing' : 'next', $cursor);
                if ($calls === 2 && $stage === 'page') {
                    throw $failure;
                }
                return $this->page($calls === 1, $calls === 1 ? 'next' : 'terminal');
            });
        $options = $this->createStub(OptionSynchronizationInterface::class);
        $optionCalls = 0;
        $options->method('executeForAttributeCodes')->willReturnCallback(
            static function () use ($stage, $failure, &$optionCalls): array {
                $optionCalls++;
                if ($stage === 'options' && $optionCalls === 2) {
                    throw $failure;
                }
                return ['mappings' => [], 'summary' => []];
            }
        );
        $savedCursor = 'existing';
        $process = $this->process($definitions, $importer, $savedCursor, $options);

        try {
            $process->executeUntilComplete();
            self::fail('The failure must stop the invocation.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame($stage === 'definitions' ? 'existing' : 'next', $savedCursor);

        $process->executeUntilComplete();
        self::assertSame('terminal', $savedCursor);
    }

    /** @return array<string, array{string}> */
    public static function failureStages(): array
    {
        return ['definitions' => ['definitions'], 'page' => ['page'], 'options' => ['options']];
    }

    private function process(
        AttributeDefinitionSynchronizationInterface $definitions,
        AttributeBatchImporterInterface $importer,
        ?string &$savedCursor,
        ?OptionSynchronizationInterface $options = null
    ): AttributeImportProcess {
        $storage = $this->createMock(CursorStorage::class);
        $storage->expects(self::atLeastOnce())->method('get')
            ->with(AttributeImportProcess::PROCESS_CODE)->willReturnCallback(
                static function () use (&$savedCursor): array {
                    return ['cursor' => $savedCursor, 'synced_at' => null];
                }
            );
        $storage->expects(self::atLeastOnce())->method('save')->willReturnCallback(
            static function (string $code, ?string $cursor) use (&$savedCursor): void {
                self::assertSame(AttributeImportProcess::PROCESS_CODE, $code);
                $savedCursor = $cursor;
            }
        );
        $storage->method('reset')->willReturnCallback(
            static function (string $code) use (&$savedCursor): void {
                self::assertSame(AttributeImportProcess::PROCESS_CODE, $code);
                $savedCursor = null;
            }
        );
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        $mapper = $this->createStub(AttributeAutoMapperInterface::class);
        $mapper->method('synchronize')->willReturn([]);
        if ($options === null) {
            $options = $this->createStub(OptionSynchronizationInterface::class);
            $options->method('executeForAttributeCodes')->willReturn(['mappings' => [], 'summary' => []]);
        }
        $batch = new AttributeSynchronizationBatch(
            $definitions,
            $importer,
            $mapper,
            $options,
            new AttributeSynchronizationOutcomePolicy(),
            $lock
        );

        return new AttributeImportProcess(
            new PersistedBatchImportRunner($storage, $lock),
            $batch,
            new ChangeReport(new Json())
        );
    }

    /** @return array<string, mixed> */
    private function page(bool $hasMore, ?string $cursor): array
    {
        return [
            'has_more' => $hasMore,
            'cursor' => $cursor,
            'page_size' => 25,
            'imported' => 1,
            'changed' => 1,
            'unchanged' => 0,
            'attribute_codes' => ['color'],
        ];
    }
}
