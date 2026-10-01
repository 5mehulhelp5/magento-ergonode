<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\AttributeConsumer\Api\AttributeBatchImporterInterface;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface;
use Ergonode\ProductAttributeConsumer\Api\AttributeAutoMapperInterface;
use Ergonode\ProductAttributeConsumer\Model\Sync\AttributeSynchronizationBatch;
use Ergonode\ProductAttributeConsumer\Model\Sync\AttributeSynchronizationOutcomePolicy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class AttributeSynchronizationBatchTest extends TestCase
{
    public function testAutomaticBatchPersistsSuggestionsAndTreatsBusinessConflictsAsReviewable(): void
    {
        $importer = $this->createStub(AttributeBatchImporterInterface::class);
        $importer->method('import')->willReturn($this->importResult());
        $autoMapper = $this->createMock(AttributeAutoMapperInterface::class);
        $autoMapper->expects(self::once())->method('synchronize')->willReturn(
            $this->mappingStats(['conflicts' => 2, 'inserted' => 1])
        );
        $options = $this->createStub(OptionSynchronizationInterface::class);
        $options->method('executeForAttributeCodes')->willReturn($this->optionResult(['errors' => 1]));

        $result = $this->batch($importer, $autoMapper, $options, $this->successfulLock())
            ->executeAutomatic(null, 200);

        self::assertSame(1, $result['mapping']['inserted']);
        self::assertSame(3, $result['completion']['review_required']);
        self::assertTrue($result['completion']['cursor_advance_allowed']);
    }

    public function testConcurrentRefreshAndAutomaticBatchesUseOneLock(): void
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())
            ->method('lock')
            ->with('ergonode_attribute_synchronization_batch', 0)
            ->willReturn(false);
        $lock->expects(self::never())->method('unlock');
        $importer = $this->createMock(AttributeBatchImporterInterface::class);
        $importer->expects(self::never())->method('import');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already running');

        $this->batch(
            $importer,
            $this->createStub(AttributeAutoMapperInterface::class),
            $this->createStub(OptionSynchronizationInterface::class),
            $lock
        )->executeAutomatic();
    }

    public function testInfrastructureFailureEscapesAndReleasesBatchLock(): void
    {
        $importer = $this->createStub(AttributeBatchImporterInterface::class);
        $importer->method('import')->willThrowException(new LocalizedException(__('GraphQL unavailable.')));
        $lock = $this->successfulLock();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('GraphQL unavailable');

        $this->batch(
            $importer,
            $this->createStub(AttributeAutoMapperInterface::class),
            $this->createStub(OptionSynchronizationInterface::class),
            $lock
        )->executeAutomatic();
    }

    private function batch(
        AttributeBatchImporterInterface $importer,
        AttributeAutoMapperInterface $autoMapper,
        OptionSynchronizationInterface $options,
        LockManagerInterface $lock
    ): AttributeSynchronizationBatch {
        return new AttributeSynchronizationBatch(
            $this->createStub(AttributeDefinitionSynchronizationInterface::class),
            $importer,
            $autoMapper,
            $options,
            new AttributeSynchronizationOutcomePolicy(),
            $lock
        );
    }

    private function successfulLock(): LockManagerInterface
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())->method('lock')->willReturn(true);
        $lock->expects(self::once())->method('unlock');

        return $lock;
    }

    /**
     * @return array<string, mixed>
     */
    private function importResult(): array
    {
        return [
            'has_more' => true,
            'cursor' => 'next',
            'page_size' => 25,
            'imported' => 1,
            'changed' => 1,
            'unchanged' => 0,
            'attribute_codes' => ['color'],
        ];
    }

    /**
     * @param  array<string, int> $overrides
     * @return array<string, int>
     */
    private function mappingStats(array $overrides = []): array
    {
        return array_replace(
            [
            'matched' => 0,
            'conflicts' => 0,
            'created' => 0,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            ],
            $overrides
        );
    }

    /**
     * @param  array<string, int> $overrides
     * @return array<string, mixed>
     */
    private function optionResult(array $overrides = []): array
    {
        return [
            'mappings' => [['mapping_id' => 1]],
            'summary' => array_replace(
                [
                'created' => 0,
                'linked' => 0,
                'mappings_inserted' => 0,
                'mappings_updated' => 0,
                'labels_updated' => 0,
                'sort_order_updated' => 0,
                'unchanged' => 0,
                'skipped' => 0,
                'errors' => 0,
                ],
                $overrides
            ),
        ];
    }
}
