<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductStateWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductStateSynchronizerPool;
use Ergonode\ProductConsumer\Model\Magento\ProductUrlKeyWriter;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Test\Unit\Support\RemoteProductAttributeFixture;
use PHPUnit\Framework\TestCase;

class ProductStateWriterTest extends TestCase
{
    public function testPreservesMappedStatusInsteadOfDerivingItFromProductLifecycleStatus(): void
    {
        $source = new RemoteProduct(
            'SKU-1',
            'simple',
            'template',
            false,
            ['pl_PL' => 'draft'],
            [RemoteProductAttributeFixture::string('enabled', 'select', ['pl_PL' => 'yes'])]
        );
        $mapped = [
            'values' => [
                'status' => [0 => 1],
                'url_key' => [0 => 'mapped-url-key'],
            ],
            'clear' => ['url_key' => [2]],
        ];
        $valueMapper = $this->createMock(ProductAttributeValueMapper::class);
        $special = ['values' => ['default_category' => [0 => 42]], 'clear' => ['default_category' => [2]]];
        $valueMapper->expects(self::once())->method('map')
            ->with($source->attributes, null, $special)->willReturn($mapped);
        $attributeWriter = $this->createMock(ProductAttributeWriter::class);
        $attributeWriter->expects(self::once())
            ->method('write')
            ->with(23, ['status' => [0 => 1]], []);
        $urlKeyWriter = $this->createMock(ProductUrlKeyWriter::class);
        $urlKeyWriter->expects(self::once())
            ->method('write')
            ->with(23, [0 => 'mapped-url-key'], [2]);
        $adapter = $this->createMock(ProductTypeAdapterInterface::class);
        $adapter->expects(self::once())->method('synchronizeRelations')->with(23, 'SKU-1', $source);
        $extension = $this->createMock(ProductStateSynchronizerInterface::class);
        $extension->expects(self::once())->method('synchronize')->with(23, 'SKU-1', $source);

        (new ProductStateWriter(
            $valueMapper,
            $attributeWriter,
            new ProductStateSynchronizerPool([$extension]),
            $urlKeyWriter,
            $this->createStub(MagentoSkuSynchronizer::class)
        ))->write(23, $source, $adapter, resolvedSpecial: $special);
    }
}
