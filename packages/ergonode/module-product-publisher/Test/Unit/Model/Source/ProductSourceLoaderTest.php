<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Source;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Model\Source\MagentoProductSourceProvider;
use Ergonode\ProductPublisher\Model\Source\ProductSourceData;
use Ergonode\ProductPublisher\Model\Source\ProductSourceLoader;
use Ergonode\ProductPublisher\Model\Source\ProductSourceResult;
use Ergonode\ProductPublisher\Model\Source\ProductSourceStateBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductSourceLoaderTest extends TestCase
{
    /** Verify that every skipped product reason reaches the application log. */
    public function testLogsWhyProductWasSkipped(): void
    {
        $source = new ProductSourceData([], [], [], [], []);
        $sourceProvider = $this->createMock(MagentoProductSourceProvider::class);
        $sourceProvider->expects($this->once())->method('load')->with(['SKU-7'])->willReturn($source);
        $result = new ProductSourceResult([], false, [
            'SKU-7' => 'Skipped Magento product "SKU-7" because attribute set ID 7 has no Ergonode template mapping.',
        ]);
        $stateBuilder = $this->createMock(ProductSourceStateBuilder::class);
        $stateBuilder->expects($this->once())->method('build')->with($source, ['SKU-7'])->willReturn($result);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Skipped Magento product "SKU-7" because attribute set ID 7 has no Ergonode template mapping.',
            ['sku' => 'SKU-7']
        );

        $actual = (new ProductSourceLoader($sourceProvider, $stateBuilder, $logger))->load(['SKU-7']);
        self::assertSame([], $actual->getStates());
        self::assertFalse($actual->isAuthoritative());
        self::assertSame($result->getSkippedProductMessages(), $actual->getSkippedProductMessages());
    }

    public function testExplicitSelectionNeverLoadsRelatedProducts(): void
    {
        $source = new ProductSourceData([], [], [], [], []);
        $provider = $this->createMock(MagentoProductSourceProvider::class);
        $provider->expects(self::once())->method('load')->with(['SELECTED'])->willReturn($source);
        $state = $this->createStub(ProductStateInterface::class);
        $relations = $this->createStub(ProductRelationStateInterface::class);
        $relations->method('getVariantSkus')->willReturn(['UNSELECTED']);
        $state->method('getRelations')->willReturn($relations);
        $state->method('getSku')->willReturn('SELECTED');
        $builder = $this->createMock(ProductSourceStateBuilder::class);
        $builder->expects(self::once())->method('build')->with($source, ['SELECTED'])->willReturn(
            new ProductSourceResult([$state], true, [])
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $loader = new ProductSourceLoader($provider, $builder, $logger);
        self::assertSame([$state], $loader->load(['SELECTED'])->getStates());
    }
}
