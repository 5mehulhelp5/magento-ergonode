<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationResultWriterInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Api\ProductBatchSynchronizerInterface;
use Ergonode\ProductPublisher\Model\Source\ProductSourceLoader;
use Ergonode\ProductPublisher\Model\Source\ProductSourceResult;
use Ergonode\ProductPublisher\Model\Sync\ProductDependencyLayerPlanner;
use Ergonode\ProductPublisher\Model\Sync\ProductIdentityCoordinator;
use Ergonode\ProductPublisher\Model\Sync\ProductIdentityPreparationResult;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationIdentityRecorder;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationSynchronizer;
use Ergonode\ProductPublisher\Model\ResourceModel\ProductPublicationResultReader;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationBatch;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductPublicationBatchTest extends TestCase
{
    public function testMixedNumericBatchPersistsConfirmedResultsAfterRemotePublication(): void
    {
        $skus = [14 => '8001', 15 => '8002', 20 => 'B040N', 3 => 'T-2785'];
        $remoteSkus = ['8001' => '1414', '8002' => '1415', 'B040N' => '1707', 'T-2785' => '740'];
        $states = [];
        $prepared = [];
        foreach ($skus as $sku) {
            $states[] = new ProductState($sku, 'simple', 'template');
            $prepared[$sku] = new ProductState($remoteSkus[$sku], 'simple', 'template');
        }
        $loader = $this->createStub(ProductSourceLoader::class);
        $loader->method('load')->willReturn(new ProductSourceResult(
            $states,
            true,
            productWarnings: ['8001' => ['Missing option mapping.'], 'B040N' => ['Missing option mapping.']]
        ));
        $coordinator = $this->createStub(ProductIdentityCoordinator::class);
        $coordinator->method('prepare')->willReturn(new ProductIdentityPreparationResult($prepared, []));
        $remote = $this->createMock(ProductBatchSynchronizerInterface::class);
        $remote->expects(self::once())->method('synchronizeBatch')->with(array_values($prepared), 'update')
            ->willReturn([
                new ProductSynchronizationResult('1707', 'noop'),
                new ProductSynchronizationResult('1415', 'success'),
                new ProductSynchronizationResult('740', 'attention', message: 'Mapped product is absent.'),
                new ProductSynchronizationResult('1414', 'success'),
            ]);
        $resolver = $this->createMock(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn($skus);
        $resolver->expects(self::once())->method('getProductSkusBySkus')->with(['8001', '8002', 'B040N'])
            ->willReturn([14 => '8001', 15 => '8002', 20 => 'B040N']);
        $identities = $this->createMock(ProductIdentityRegistryInterface::class);
        $identities->method('getStabilityFailures')->willReturn([]);
        $identities->method('getIdentitiesByProductIds')->willReturn([]);
        $identities->expects(self::once())->method('recordPublished')
            ->with([14 => '8001', 15 => '8002', 20 => 'B040N']);
        $sync = new ProductPublicationSynchronizer(
            $loader,
            new ProductDependencyLayerPlanner(),
            $coordinator,
            $remote,
            new ProductPublicationIdentityRecorder($resolver, $identities)
        );
        $saved = [];
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (array $items) use (&$saved): string {
                $saved[] = $items;
                return '2026-09-27 21:18:03';
            }
        );
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn(ProductIdentityModeProviderInterface::MODE_MAPPED);

        $items = $this->batch($resolver, $identities, $sync, $writer, $mode)->publish(array_keys($skus));

        self::assertSame(
            ['unconfirmed', 'unconfirmed', 'unconfirmed', 'unconfirmed'],
            array_column($saved[0], 'status')
        );
        self::assertSame(['warning', 'success', 'warning', 'unconfirmed'], array_column($saved[1], 'status'));
        self::assertSame(array_values($skus), array_column($items, 'code'));
        self::assertSame(['warning', 'success', 'warning', 'unconfirmed'], array_column($items, 'status'));
        self::assertSame('Missing option mapping.', $items[0]['message']);
        self::assertSame('Mapped product is absent.', $items[3]['message']);
    }

    public function testOnlyExplicitPublishableProductsAreSentAndEveryIdHasAResult(): void
    {
        $resolver = $this->createMock(ProductPublicationProductResolverInterface::class);
        $resolver->expects(self::once())->method('getCurrentSkus')->with([1, 2, 3, 4])->willReturn([
            1 => 'OK', 2 => 'CHANGED', 4 => 'ATTENTION',
        ]);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getStabilityFailures')->willReturn([2 => 'Immutable SKU changed.']);
        $sync = $this->createMock(ProductPublicationSynchronizerInterface::class);
        $sync->expects(self::once())->method('synchronize')->with(['OK', 'ATTENTION'])->willReturn([
            'OK' => new ProductSynchronizationResult('OK', 'success'),
            'ATTENTION' => new ProductSynchronizationResult(
                'ATTENTION',
                'attention',
                message: 'Check remote identity.'
            ),
        ]);
        $saved = [];
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (array $items) use (&$saved): string {
                $saved[] = $items;
                return '2026-09-10 18:30:00';
            }
        );
        $results = $this->batch($resolver, $identities, $sync, $writer)->publish([1, 2, 3, 4]);
        self::assertSame([1, 2, 4], array_column($saved[0], 'product_id'));
        self::assertSame(['unconfirmed', 'unconfirmed', 'unconfirmed'], array_column($saved[0], 'status'));
        self::assertSame(['success', 'warning', 'warning', 'unconfirmed'], array_column($saved[1], 'status'));
        self::assertSame('2026-09-10 18:30:00', $results[0]['publication_at']);
        self::assertSame([1, 2, 3, 4], array_column($results, 'product_id'));
        self::assertSame(['success', 'warning', 'warning', 'unconfirmed'], array_column($results, 'status'));
        self::assertSame('Immutable SKU changed.', $results[1]['message']);
        self::assertSame('The Magento product no longer exists.', $results[2]['message']);
    }

    public function testOversizedBatchCannotReachPublisher(): void
    {
        $sync = $this->createMock(ProductPublicationSynchronizerInterface::class);
        $sync->expects(self::never())->method('synchronize');
        $service = $this->batch(
            $this->createStub(ProductPublicationProductResolverInterface::class),
            $this->createStub(ProductIdentityRegistryInterface::class),
            $sync,
            $this->createStub(ProductPublicationResultWriterInterface::class)
        );
        $this->expectException(LocalizedException::class);
        $service->publish(range(1, 51));
    }

    public function testConfirmedWarningIsPersistedForExistingPopup(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([21 => 'T-2105']);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getStabilityFailures')->willReturn([]);
        $sync = $this->createStub(ProductPublicationSynchronizerInterface::class);
        $sync->method('synchronize')->willReturn(['T-2105' => new ProductSynchronizationResult(
            'T-2105',
            'success',
            warnings: [
                'Product "T-2105": omitted attribute "product_type_pim"; ID 7788.',
                'Product "T-2105": omitted attribute "navireo_synchro"; ID 1.',
            ]
        )]);
        $saved = [];
        $writer = $this->createStub(ProductPublicationResultWriterInterface::class);
        $writer->method('save')->willReturnCallback(static function (array $items) use (&$saved): string {
            $saved[] = $items;
            return '2026-09-27 12:00:00';
        });
        $items = $this->batch($resolver, $identities, $sync, $writer)->publish([21]);
        self::assertSame('warning', $items[0]['status']);
        self::assertSame($items[0]['message'], $saved[1][0]['message']);
        self::assertSame('warning', $saved[1][0]['status']);
        self::assertStringContainsString('product_type_pim', $items[0]['message']);
        self::assertStringContainsString('navireo_synchro', $items[0]['message']);
        self::assertCount(2, explode("\n", $items[0]['message']));
    }

    public function testRemoteFailedResultStaysFailedWithoutLocalWarnings(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([21 => 'T-2105']);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getStabilityFailures')->willReturn([]);
        $sync = $this->createStub(ProductPublicationSynchronizerInterface::class);
        $sync->method('synchronize')->willReturn(['T-2105' => new ProductSynchronizationResult(
            'T-2105',
            'failed',
            message: 'Ergonode rejected missing required attribute.'
        )]);
        $writer = $this->createStub(ProductPublicationResultWriterInterface::class);
        $writer->method('save')->willReturn('2026-09-27 12:00:00');

        $items = $this->batch($resolver, $identities, $sync, $writer)->publish([21]);
        self::assertSame('failed', $items[0]['status']);
        self::assertSame('Ergonode rejected missing required attribute.', $items[0]['message']);
    }

    public function testInterruptedPublicationPersistsAnUnconfirmedResultAndRethrows(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([1 => 'SKU-1']);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $sync = $this->createStub(ProductPublicationSynchronizerInterface::class);
        $sync->method('synchronize')->willThrowException(new LocalizedException(__('Connection lost.')));
        $saved = [];
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (array $items) use (&$saved): string {
                $saved[] = $items;
                return '2026-09-10 18:30:00';
            }
        );
        try {
            $this->batch($resolver, $identities, $sync, $writer)->publish([1]);
            self::fail('Expected the original failure to reach the caller.');
        } catch (LocalizedException $exception) {
            self::assertSame('Connection lost.', $exception->getMessage());
        }
        self::assertSame('unconfirmed', $saved[1][0]['status']);
        self::assertSame('Connection lost.', $saved[1][0]['message']);
    }

    public function testUnconfirmedAssignedCreateCannotCreateAnotherProductOnRetry(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([21 => 'T-2105']);
        $identities = $this->createMock(ProductIdentityRegistryInterface::class);
        $identities->expects(self::once())->method('getIdentitiesByProductIds')->with([21])->willReturn([]);
        $identities->method('getStabilityFailures')->willReturn([]);
        $sync = $this->createMock(ProductPublicationSynchronizerInterface::class);
        $sync->expects(self::never())->method('synchronize');
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::never())->method('save');
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn(ProductIdentityModeProviderInterface::MODE_ASSIGNED);
        $reader = $this->createMock(ProductPublicationResultReader::class);
        $reader->expects(self::once())->method('getUnconfirmedTimes')->with([21])
            ->willReturn([21 => '2026-09-27 12:00:00']);

        $items = $this->batch($resolver, $identities, $sync, $writer, $mode, $reader)->publish([21]);

        self::assertSame('unconfirmed', $items[0]['status']);
        self::assertSame('2026-09-27 12:00:00', $items[0]['publication_at']);
        self::assertStringContainsString('No new product was created', $items[0]['message']);
    }

    public function testUnconfirmedMappedCreateWithoutBindingRetriesThroughSynchronizer(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([21 => 'MAG-21']);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getStabilityFailures')->willReturn([]);
        $sync = $this->createMock(ProductPublicationSynchronizerInterface::class);
        $sync->expects(self::once())->method('synchronize')->with(['MAG-21'])->willReturn([
            'MAG-21' => new ProductSynchronizationResult('MAG-21', 'success'),
        ]);
        $saved = [];
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (array $items) use (&$saved): string {
                $saved[] = $items;
                return '2026-09-27 13:00:00';
            }
        );
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn(ProductIdentityModeProviderInterface::MODE_MAPPED);
        $reader = $this->createStub(ProductPublicationResultReader::class);
        $reader->method('getUnconfirmedTimes')->willReturn([21 => '2026-09-27 12:00:00']);

        $items = $this->batch($resolver, $identities, $sync, $writer, $mode, $reader)->publish([21]);

        self::assertSame('unconfirmed', $saved[0][0]['status']);
        self::assertSame('success', $saved[1][0]['status']);
        self::assertSame('success', $items[0]['status']);
        self::assertSame('2026-09-27 13:00:00', $items[0]['publication_at']);
    }

    public function testReconciledIdentityAllowsPublicationAfterUnconfirmedCreate(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getCurrentSkus')->willReturn([21 => 'T-2105']);
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identities = $this->createStub(ProductIdentityRegistryInterface::class);
        $identities->method('getIdentitiesByProductIds')->willReturn([21 => $identity]);
        $identities->method('getStabilityFailures')->willReturn([]);
        $sync = $this->createMock(ProductPublicationSynchronizerInterface::class);
        $sync->expects(self::once())->method('synchronize')->with(['T-2105'])->willReturn([
            'T-2105' => new ProductSynchronizationResult('T-2105', 'success'),
        ]);
        $writer = $this->createMock(ProductPublicationResultWriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')->willReturn('2026-09-27 13:00:00');
        $mode = $this->createStub(ProductIdentityModeProviderInterface::class);
        $mode->method('getMode')->willReturn(ProductIdentityModeProviderInterface::MODE_ASSIGNED);
        $reader = $this->createStub(ProductPublicationResultReader::class);
        $reader->method('getUnconfirmedTimes')->willReturn([21 => '2026-09-27 12:00:00']);

        $items = $this->batch($resolver, $identities, $sync, $writer, $mode, $reader)->publish([21]);

        self::assertSame('success', $items[0]['status']);
    }

    private function batch(
        ProductPublicationProductResolverInterface $resolver,
        ProductIdentityRegistryInterface $identities,
        ProductPublicationSynchronizerInterface $sync,
        ProductPublicationResultWriterInterface $writer,
        ?ProductIdentityModeProviderInterface $mode = null,
        ?ProductPublicationResultReader $reader = null
    ): ProductPublicationBatch {
        return new ProductPublicationBatch(
            $resolver,
            $identities,
            $sync,
            $writer,
            $mode ?? $this->createStub(ProductIdentityModeProviderInterface::class),
            $reader ?? $this->createStub(ProductPublicationResultReader::class)
        );
    }
}
