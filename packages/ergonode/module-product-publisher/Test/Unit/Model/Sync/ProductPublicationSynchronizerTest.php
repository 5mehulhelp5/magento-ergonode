<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Api\ProductSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Data\ProductRelationState;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\ProductPublisher\Model\Source\ProductSourceLoader;
use Ergonode\ProductPublisher\Model\Source\ProductSourceResult;
use Ergonode\ProductPublisher\Model\Sync\ProductDependencyLayerPlanner;
use Ergonode\ProductPublisher\Model\Sync\ProductIdentityCoordinator;
use Ergonode\ProductPublisher\Model\Sync\ProductIdentityPreparationResult;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationIdentityRecorder;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationSynchronizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\Exception\LocalizedException;

class ProductPublicationSynchronizerTest extends TestCase
{
    #[DataProvider('numericResultCases')]
    public function testNumericSkuReturnsItsOwnResult(
        string $scenario,
        string $expectedStatus,
        string $expectedMessage
    ): void {
        $sourceState = new ProductState('8001', 'simple', 'template');
        $remoteState = new ProductState('1414', 'simple', 'template');
        $loader = $this->createStub(ProductSourceLoader::class);
        $loader->method('load')->willReturn(new ProductSourceResult(
            [$sourceState],
            true,
            productWarnings: ['8001' => ['Missing option mapping.']]
        ));
        $coordinator = $this->createStub(ProductIdentityCoordinator::class);
        $coordinator->method('prepare')->willReturn($scenario === 'identity_failure'
            ? new ProductIdentityPreparationResult([], [
                '8001' => ['status' => 'failed', 'message' => 'Identity failed.'],
            ])
            : new ProductIdentityPreparationResult(['8001' => $remoteState], []));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        if ($scenario === 'identity_failure') {
            $remote->expects(self::never())->method('synchronizeBatch');
        } elseif ($scenario === 'exception') {
            $remote->expects(self::once())->method('synchronizeBatch')
                ->willThrowException(new LocalizedException(__('Remote failure.')));
        } else {
            $remote->expects(self::once())->method('synchronizeBatch')->willReturn($scenario === 'missing'
                ? []
                : [new ProductSynchronizationResult('1414', $expectedStatus, message: $expectedMessage)]);
        }
        $recorder = $this->createMock(ProductPublicationIdentityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::callback(
            static fn (array $results): bool => count($results) === 1
                && $results[0]->getSku() === '8001'
                && $results[0]->getStatus() === $expectedStatus
        ));

        $results = (new ProductPublicationSynchronizer(
            $loader,
            new ProductDependencyLayerPlanner(),
            $coordinator,
            $remote,
            $recorder
        ))->synchronize(['8001']);

        self::assertSame('8001', $results['8001']->getSku());
        self::assertSame($expectedStatus, $results['8001']->getStatus());
        self::assertSame($expectedMessage, $results['8001']->getMessage());
        self::assertSame(
            in_array($expectedStatus, ['success', 'noop'], true) ? ['Missing option mapping.'] : [],
            $results['8001']->getWarnings()
        );
    }

    /** @return array<string, array{string, string, string}> */
    public static function numericResultCases(): array
    {
        return [
            'remote success with warning' => ['success', 'success', ''],
            'remote noop with warning' => ['noop', 'noop', ''],
            'remote failure' => ['failed', 'failed', 'Ergonode rejected the product.'],
            'remote attention' => ['attention', 'attention', 'Mapped product is absent.'],
            'identity failure' => ['identity_failure', 'failed', 'Identity failed.'],
            'missing result' => ['missing', 'failed', 'Product synchronizer returned no correlated result.'],
            'remote exception' => ['exception', 'failed', 'Remote failure.'],
        ];
    }

    public function testNumericSourceFailuresRemainPerProductAndBlockDependants(): void
    {
        $loader = $this->createStub(ProductSourceLoader::class);
        $loader->method('load')->willReturn(new ProductSourceResult(
            [new ProductState(
                '8003',
                'grouping',
                'template',
                relations: new ProductRelationState(groupedChildren: ['8001' => 1])
            )],
            false,
            ['8001' => 'Invalid source.'],
            skippedProductWarnings: ['8002' => 'Missing template.']
        ));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        $remote->expects(self::never())->method('synchronizeBatch');
        $results = $this->synchronizer(
            $loader,
            $remote,
            $this->createStub(ProductPublicationIdentityRecorder::class)
        )->synchronize(['8001', '8002', '8003']);

        self::assertSame('8001', $results['8001']->getSku());
        self::assertSame('failed', $results['8001']->getStatus());
        self::assertSame('Invalid source.', $results['8001']->getMessage());
        self::assertSame('8002', $results['8002']->getSku());
        self::assertSame('local_warning', $results['8002']->getStatus());
        self::assertSame('Missing template.', $results['8002']->getMessage());
        self::assertSame('blocked_reference', $results['8003']->getStatus());
    }

    public function testReturnsSourceFailureWithoutCallingRemoteSynchronizer(): void
    {
        $sourceLoader = $this->createMock(ProductSourceLoader::class);
        $sourceLoader->expects(self::once())->method('load')->with(['SKU-1'])->willReturn(
            new ProductSourceResult([], false, ['SKU-1' => 'Missing template mapping.'])
        );
        $batchSynchronizer = $this->createMock(ProductBatchSynchronizerInterface::class);
        $batchSynchronizer->expects(self::never())->method('synchronizeBatch');
        $recorder = $this->createMock(ProductPublicationIdentityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::callback(
            static fn (array $results): bool => count($results) === 1
                && $results[0]->getStatus() === ProductSynchronizationResultInterface::STATUS_FAILED
        ));

        $results = $this->synchronizer($sourceLoader, $batchSynchronizer, $recorder)->synchronize(['SKU-1']);

        self::assertSame('Missing template mapping.', $results['SKU-1']->getMessage());
    }

    public function testUnsuitableLocalProductIsWarningWithoutSuccessfulIdentityRecord(): void
    {
        $sourceLoader = $this->createStub(ProductSourceLoader::class);
        $sourceLoader->method('load')->willReturn(new ProductSourceResult(
            [],
            false,
            [],
            [],
            ['SKU-1' => 'No template mapping for SKU-1.']
        ));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        $remote->expects(self::never())->method('synchronizeBatch');
        $recorder = $this->createMock(ProductPublicationIdentityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::callback(
            static fn (array $results): bool => count($results) === 1 && !$results[0]->isSuccessful()
        ));

        $result = $this->synchronizer($sourceLoader, $remote, $recorder)->synchronize(['SKU-1'])['SKU-1'];
        self::assertSame(ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING, $result->getStatus());
        self::assertSame('No template mapping for SKU-1.', $result->getMessage());
    }

    public function testMissingSourceStateDoesNotClaimMagentoProductIsAbsent(): void
    {
        $sourceLoader = $this->createStub(ProductSourceLoader::class);
        $sourceLoader->method('load')->willReturn(new ProductSourceResult([], false, []));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        $remote->expects(self::never())->method('synchronizeBatch');
        $recorder = $this->createStub(ProductPublicationIdentityRecorder::class);

        $result = $this->synchronizer($sourceLoader, $remote, $recorder)->synchronize(['T-2105'])['T-2105'];

        self::assertSame(ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING, $result->getStatus());
        self::assertSame(
            'The publication source returned no state or skip reason for the selected Magento SKU.',
            $result->getMessage()
        );
    }

    public function testDependentProductWaitsWhenSelectedPrerequisiteWasSkippedLocally(): void
    {
        $parent = new ProductState(
            'PARENT',
            'variable',
            'template',
            relations: new ProductRelationState(['binding'], ['CHILD'])
        );
        $sourceLoader = $this->createStub(ProductSourceLoader::class);
        $sourceLoader->method('load')->willReturn(new ProductSourceResult(
            [$parent],
            false,
            [],
            [],
            ['CHILD' => 'No template mapping for CHILD.']
        ));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        $remote->expects(self::never())->method('synchronizeBatch');
        $recorder = $this->createStub(ProductPublicationIdentityRecorder::class);

        $results = $this->synchronizer($sourceLoader, $remote, $recorder)->synchronize(['PARENT', 'CHILD']);
        self::assertSame(ProductSynchronizationResultInterface::STATUS_LOCAL_WARNING, $results['CHILD']->getStatus());
        self::assertSame(
            ProductSynchronizationResultInterface::STATUS_BLOCKED_REFERENCE,
            $results['PARENT']->getStatus()
        );
    }

    public function testSynchronizesDependencyFirstAndReturnsMagentoSkuKeys(): void
    {
        $child = new ProductState('CHILD', 'simple', 'template');
        $parent = new ProductState(
            'PARENT',
            'variable',
            'template',
            relations: new ProductRelationState(['binding'], ['CHILD'])
        );
        $sourceLoader = $this->createStub(ProductSourceLoader::class);
        $sourceLoader->method('load')->willReturn(new ProductSourceResult(
            [$parent, $child],
            true,
            [],
            ['CHILD' => ['CHILD omitted attribute "color": ID 7788.']]
        ));
        $coordinator = $this->createMock(ProductIdentityCoordinator::class);
        $coordinator->expects(self::exactly(2))->method('prepare')->willReturnCallback(
            static fn (array $states): ProductIdentityPreparationResult =>
                new ProductIdentityPreparationResult($states, [])
        );
        $batchSynchronizer = $this->createMock(ProductBatchSynchronizerInterface::class);
        $expectedLayers = [['CHILD'], ['PARENT']];
        $batchSynchronizer->expects(self::exactly(2))
            ->method('synchronizeBatch')
            ->with(
                self::callback(static function (array $states) use (&$expectedLayers): bool {
                    return array_map(static fn (ProductState $state): string => $state->getSku(), $states)
                        === array_shift($expectedLayers);
                }),
                ProductSynchronizerInterface::MODE_UPDATE
            )
            ->willReturnCallback(static fn (array $states): array => [
                new ProductSynchronizationResult(
                    $states[0]->getSku(),
                    ProductSynchronizationResultInterface::STATUS_SUCCESS
                ),
            ]);
        $recorder = $this->createMock(ProductPublicationIdentityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::callback(
            static fn (array $results): bool => count($results) === 2
                && $results[0]->getStatus() === ProductSynchronizationResultInterface::STATUS_SUCCESS
                && $results[0]->getWarnings() === ['CHILD omitted attribute "color": ID 7788.']
        ));

        $synchronizer = new ProductPublicationSynchronizer(
            $sourceLoader,
            new ProductDependencyLayerPlanner(),
            $coordinator,
            $batchSynchronizer,
            $recorder
        );
        $results = $synchronizer->synchronize(['PARENT']);

        self::assertSame(['CHILD', 'PARENT'], array_keys($results));
        self::assertTrue($results['CHILD']->isSuccessful());
        self::assertSame(ProductSynchronizationResultInterface::STATUS_SUCCESS, $results['PARENT']->getStatus());
        self::assertSame([], $expectedLayers);
    }

    public function testFailedRemoteMutationNeverGainsSourceWarning(): void
    {
        $state = new ProductState('T-2105', 'simple', 'template');
        $loader = $this->createStub(ProductSourceLoader::class);
        $loader->method('load')->willReturn(new ProductSourceResult(
            [$state],
            true,
            [],
            ['T-2105' => ['Missing option 7788.']]
        ));
        $coordinator = $this->createStub(ProductIdentityCoordinator::class);
        $coordinator->method('prepare')->willReturn(new ProductIdentityPreparationResult(['T-2105' => $state], []));
        $remote = $this->createStub(ProductBatchSynchronizerInterface::class);
        $remote->method('synchronizeBatch')->willReturn([
            new ProductSynchronizationResult(
                'T-2105',
                ProductSynchronizationResultInterface::STATUS_FAILED,
                message: 'GraphQL mutation failed.'
            ),
        ]);
        $recorder = $this->createMock(ProductPublicationIdentityRecorder::class);
        $recorder->expects(self::once())->method('record')->with(self::callback(
            static fn (array $results): bool => $results[0]->getWarnings() === []
                && !$results[0]->isSuccessful()
        ));
        $result = (new ProductPublicationSynchronizer(
            $loader,
            new ProductDependencyLayerPlanner(),
            $coordinator,
            $remote,
            $recorder
        ))->synchronize(['T-2105'])['T-2105'];
        self::assertSame(ProductSynchronizationResultInterface::STATUS_FAILED, $result->getStatus());
        self::assertSame('GraphQL mutation failed.', $result->getMessage());
        self::assertSame([], $result->getWarnings());
    }

    private function synchronizer(
        ProductSourceLoader $sourceLoader,
        ProductBatchSynchronizerInterface $batchSynchronizer,
        ProductPublicationIdentityRecorder $recorder
    ): ProductPublicationSynchronizer {
        return new ProductPublicationSynchronizer(
            $sourceLoader,
            new ProductDependencyLayerPlanner(),
            $this->createStub(ProductIdentityCoordinator::class),
            $batchSynchronizer,
            $recorder
        );
    }
}
