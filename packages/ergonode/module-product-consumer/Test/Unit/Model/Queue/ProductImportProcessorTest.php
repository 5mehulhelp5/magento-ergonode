<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Queue;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Model\Data\PreparedProduct;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductDeletionPolicy;
use Ergonode\ProductConsumer\Model\Magento\ProductStateWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductTargetPreparer;
use Ergonode\ProductConsumer\Model\Queue\ProductImportHashProviderPool;
use Ergonode\ProductConsumer\Model\Queue\ProductImportProcessor;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use PHPUnit\Framework\TestCase;

class ProductImportProcessorTest extends TestCase
{
    public function testMappedIdentityAttributeIsCheckedEvenWhenPayloadHashIsUnchanged(): void
    {
        $source = new RemoteProduct('NAV-1', 'simple', 'template', false, [], []);
        $loader = $this->createStub(RemoteProductLoader::class);
        $loader->method('loadCurrent')->willReturn($source);
        $mapper = $this->createStub(ProductAttributeValueMapper::class);
        $mapper->method('mapSpecial')->willReturn(['values' => [], 'clear' => []]);
        $adapter = $this->createStub(ProductTypeAdapterInterface::class);
        $preparer = $this->createStub(ProductTargetPreparer::class);
        $preparer->method('prepare')->willReturn(new PreparedProduct(
            23,
            $adapter,
            'MAG-1',
            'MAG-1',
            ProductIdentityInterface::MODE_MAPPED
        ));
        $skuSynchronizer = $this->createMock(MagentoSkuSynchronizer::class);
        $skuSynchronizer->expects(self::once())->method('synchronizeIdentityAttribute')->with(
            23,
            'NAV-1',
            ProductIdentityInterface::MODE_MAPPED
        );
        $identity = $this->createStub(ProductIdentityServiceInterface::class);
        $identity->method('getImportHash')->willReturnCallback(
            static fn (): string => hash('sha256', 'product-import-v2:' . $source->contentHash()
                . ':' . json_encode(['values' => [], 'clear' => []], JSON_THROW_ON_ERROR)
                . ':[]')
        );
        $writer = $this->createMock(ProductStateWriter::class);
        $writer->expects(self::never())->method('write');
        $writer->expects(self::once())->method('synchronizeUnchanged')->with(23, 'MAG-1', $source);
        $processor = new ProductImportProcessor(
            $loader,
            $this->createStub(ProductDeletionPolicy::class),
            $preparer,
            $writer,
            $identity,
            $mapper,
            $skuSynchronizer,
            new ProductImportHashProviderPool()
        );

        self::assertFalse($processor->process(new ProductImportWorkItem(
            1,
            'NAV-1',
            ProductImportWorkItem::OPERATION_SYNCHRONIZE,
            null,
            'event',
            'lease',
            0
        )));
    }

    public function testForwardsResolvedValuesAndClearIntentsToWrite(): void
    {
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
        $special = ['values' => ['default_category' => [0 => 42]], 'clear' => ['default_category' => [2]]];
        $loader = $this->createStub(RemoteProductLoader::class);
        $loader->method('loadCurrent')->willReturn($source);
        $mapper = $this->createMock(ProductAttributeValueMapper::class);
        $mapper->expects(self::once())->method('mapSpecial')->with($source->attributes)->willReturn($special);
        $adapter = $this->createStub(ProductTypeAdapterInterface::class);
        $preparer = $this->createMock(ProductTargetPreparer::class);
        $preparer->expects(self::once())->method('prepare')->with($source, $special['values'])
            ->willReturn(new PreparedProduct(23, $adapter, 'SKU-1', 'SKU-1'));
        $writer = $this->createMock(ProductStateWriter::class);
        $writer->expects(self::once())->method('write')->with(
            23,
            $source,
            $adapter,
            'SKU-1',
            $special,
            ProductIdentityInterface::MODE_SHARED
        );
        $identity = $this->createStub(ProductIdentityServiceInterface::class);
        $identity->method('getImportHash')->willReturn(null);
        $processor = new ProductImportProcessor(
            $loader,
            $this->createStub(ProductDeletionPolicy::class),
            $preparer,
            $writer,
            $identity,
            $mapper,
            $this->createStub(MagentoSkuSynchronizer::class),
            new ProductImportHashProviderPool()
        );

        self::assertTrue($processor->process(new ProductImportWorkItem(
            1,
            'SKU-1',
            ProductImportWorkItem::OPERATION_SYNCHRONIZE,
            null,
            'event',
            'lease',
            0
        )));
    }
}
