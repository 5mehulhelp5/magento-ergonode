<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\Import;

use Ergonode\TemplateConsumer\Model\Import\ImportedTemplateProcessor;
use Ergonode\TemplateConsumer\Model\Import\TemplateListImporter;
use Ergonode\TemplateConsumer\Model\Import\TemplateListPageReader;
use Ergonode\TemplateConsumer\Model\Import\TemplateSnapshotReconciler;
use Ergonode\TemplateConsumer\Model\Import\TemplateSynchronizationProcessor;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class TemplateListImporterTest extends TestCase
{
    public function testReadsCompleteSnapshotBeforeSynchronizingIncludingEarlierDeletions(): void
    {
        $reader = $this->createMock(TemplateListPageReader::class);
        $reader->expects(self::exactly(2))->method('read')->willReturnOnConsecutiveCalls(
            ['codes' => ['a'], 'cursor' => 'page-1', 'has_more' => true],
            ['codes' => ['b'], 'cursor' => null, 'has_more' => false]
        );
        $order = [];
        $processor = $this->createMock(ImportedTemplateProcessor::class);
        $processor->expects(self::once())->method('process')->with(['a', 'b'])
            ->willReturnCallback(static function () use (&$order): array {
                $order[] = 'snapshot';
                return ['imported' => 1, 'changed' => 1, 'unchanged' => 1];
            });
        $reconciler = $this->createMock(TemplateSnapshotReconciler::class);
        $reconciler->expects(self::once())->method('reconcile')->with(['a', 'b'])
            ->willReturnCallback(static function () use (&$order): int {
                $order[] = 'reconcile';
                return 1;
            });
        $reconciler->method('getDeletedCodes')->willReturn(['deleted', 'previously_deleted']);
        $synchronizer = $this->createMock(TemplateSynchronizationProcessor::class);
        $synchronizer->expects(self::once())->method('execute')->with(['a', 'b'], ['deleted', 'previously_deleted'])
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'magento';
            });
        $result = (new TemplateListImporter($reader, $processor, $reconciler, $synchronizer))->execute();
        self::assertSame(['snapshot', 'reconcile', 'magento'], $order);
        self::assertSame(['events' => 2, 'imported' => 1, 'changed' => 2, 'unchanged' => 1, 'cursor' => null], $result);
    }

    public function testRefreshNeverInvokesMagentoSynchronization(): void
    {
        $reader = $this->createStub(TemplateListPageReader::class);
        $reader->method('read')->willReturn(['codes' => [], 'cursor' => null, 'has_more' => false]);
        $processor = $this->createStub(ImportedTemplateProcessor::class);
        $processor->method('process')->willReturn(['imported' => 0, 'changed' => 0, 'unchanged' => 0]);
        $reconciler = $this->createStub(TemplateSnapshotReconciler::class);
        $reconciler->method('reconcile')->willReturn(1);
        $synchronizer = $this->createMock(TemplateSynchronizationProcessor::class);
        $synchronizer->expects(self::never())->method('execute');
        (new TemplateListImporter($reader, $processor, $reconciler, $synchronizer))->execute(false);
    }

    public function testFailedStructureImportCannotMarkTemplatesDeletedOrSynchronizeMagento(): void
    {
        $reader = $this->createStub(TemplateListPageReader::class);
        $reader->method('read')->willReturn(['codes' => ['a'], 'cursor' => null, 'has_more' => false]);
        $processor = $this->createStub(ImportedTemplateProcessor::class);
        $processor->method('process')->willThrowException(new LocalizedException(__('Incomplete section response.')));
        $reconciler = $this->createMock(TemplateSnapshotReconciler::class);
        $reconciler->expects(self::never())->method('reconcile');
        $synchronizer = $this->createMock(TemplateSynchronizationProcessor::class);
        $synchronizer->expects(self::never())->method('execute');
        $this->expectException(LocalizedException::class);
        (new TemplateListImporter($reader, $processor, $reconciler, $synchronizer))->execute();
    }

    public function testCursorCycleCannotProduceACompletedSnapshot(): void
    {
        $reader = $this->createStub(TemplateListPageReader::class);
        $reader->method('read')->willReturnOnConsecutiveCalls(
            ['codes' => ['a'], 'cursor' => 'one', 'has_more' => true],
            ['codes' => ['b'], 'cursor' => 'two', 'has_more' => true],
            ['codes' => ['c'], 'cursor' => 'one', 'has_more' => true]
        );
        $processor = $this->createMock(ImportedTemplateProcessor::class);
        $processor->expects(self::never())->method('process');
        $reconciler = $this->createMock(TemplateSnapshotReconciler::class);
        $reconciler->expects(self::never())->method('reconcile');
        $synchronizer = $this->createMock(TemplateSynchronizationProcessor::class);
        $synchronizer->expects(self::never())->method('execute');
        $this->expectException(LocalizedException::class);
        (new TemplateListImporter($reader, $processor, $reconciler, $synchronizer))->execute();
    }
}
