<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Api\UnchangedProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductStateSynchronizerPool;
use Ergonode\ProductConsumer\Model\Magento\ProductStateWriter;
use Ergonode\ProductConsumer\Model\Magento\ProductUrlKeyWriter;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use PHPUnit\Framework\TestCase;

class UnchangedProductStateTest extends TestCase
{
    public function testHashSkipOnlyInvokesExplicitlyOptedInStateWithoutRewritingOrdinaryAttributes(): void
    {
        $source = new RemoteProduct('ERGO-1', 'simple', 'template', false, [], []);
        $media = $this->createMock(UnchangedProductStateSynchronizerInterface::class);
        $media->expects(self::once())->method('synchronizeUnchanged')->with(23, 'MAG-1', $source);
        $media->expects(self::never())->method('synchronize');
        $other = $this->createMock(ProductStateSynchronizerInterface::class);
        $other->expects(self::never())->method('synchronize');
        $mapper = $this->createMock(ProductAttributeValueMapper::class);
        $mapper->expects(self::never())->method('map');
        $attributes = $this->createMock(ProductAttributeWriter::class);
        $attributes->expects(self::never())->method('write');
        $urls = $this->createMock(ProductUrlKeyWriter::class);
        $urls->expects(self::never())->method('write');
        (new ProductStateWriter($mapper, $attributes, new ProductStateSynchronizerPool([$other, $media]),
            $urls, $this->createStub(MagentoSkuSynchronizer::class)))->synchronizeUnchanged(23, 'MAG-1', $source);
    }
}
