<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\ProductPublisher\Api\ProductIdentityRegistryInterface;
use Ergonode\ProductPublisher\Api\ProductPublicationProductResolverInterface;
use Ergonode\ProductPublisher\Model\Data\ProductSynchronizationResult;
use Ergonode\ProductPublisher\Model\Sync\ProductPublicationIdentityRecorder;
use PHPUnit\Framework\TestCase;

class ProductPublicationIdentityRecorderTest extends TestCase
{
    public function testRecordsOnlySuccessfullyPublishedRootProducts(): void
    {
        $resolver = $this->createMock(ProductPublicationProductResolverInterface::class);
        $resolver->expects(self::once())
            ->method('getProductSkusBySkus')
            ->with(['SKU-A', 'SKU-B'])
            ->willReturn([10 => 'SKU-A', 20 => 'SKU-B']);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::once())
            ->method('recordPublished')
            ->with([10 => 'SKU-A', 20 => 'SKU-B']);
        $results = [
            new ProductSynchronizationResult(
                'SKU-B',
                ProductSynchronizationResultInterface::STATUS_NOOP
            ),
            new ProductSynchronizationResult(
                'SKU-FAILED',
                ProductSynchronizationResultInterface::STATUS_FAILED
            ),
            new ProductSynchronizationResult(
                'SKU-A',
                ProductSynchronizationResultInterface::STATUS_SUCCESS
            ),
        ];

        (new ProductPublicationIdentityRecorder($resolver, $registry))->record($results);
    }

    public function testSkipsResolutionWhenNoProductWasPublished(): void
    {
        $resolver = $this->createMock(ProductPublicationProductResolverInterface::class);
        $resolver->expects(self::never())->method('getProductSkusBySkus');
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->expects(self::never())->method('recordPublished');
        $results = [
            new ProductSynchronizationResult(
                'SKU-FAILED',
                ProductSynchronizationResultInterface::STATUS_FAILED
            ),
        ];

        (new ProductPublicationIdentityRecorder($resolver, $registry))->record($results);
    }

    public function testBindsAttributeSkuBeforeRecordingSuccessfulPublication(): void
    {
        $resolver = $this->createStub(ProductPublicationProductResolverInterface::class);
        $resolver->method('getProductSkusBySkus')->willReturn([31 => 'MAG-31']);
        $registry = $this->createMock(ProductIdentityRegistryInterface::class);
        $registry->method('getIdentitiesByProductIds')->willReturn([]);
        $bound = false;
        $registry->expects(self::once())->method('bindMappedBatch')->with([31 => 'NAV-31'])
            ->willReturnCallback(static function () use (&$bound): void {
                $bound = true;
            });
        $registry->expects(self::once())->method('recordPublished')->with([31 => 'MAG-31'])
            ->willReturnCallback(static function () use (&$bound): void {
                self::assertTrue($bound);
            });
        $state = $this->createStub(ProductStateInterface::class);
        $state->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_MAPPED);
        $state->method('getErgonodeSku')->willReturn('NAV-31');

        (new ProductPublicationIdentityRecorder($resolver, $registry))->record([
            new ProductSynchronizationResult('MAG-31', ProductSynchronizationResultInterface::STATUS_SUCCESS),
        ], ['MAG-31' => $state]);
    }
}
