<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductConsumer\Model\Magento\ProductAttributeWriter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductAttributeWriterTest extends TestCase
{
    public function testConflictingWebsiteValuesAreRejectedBeforeDatabaseAccess(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $productResource = $this->createStub(ProductResource::class);
        $productResource->method('getLinkField')->willReturn('entity_id');
        $productResource->method('getIdFieldName')->willReturn('entity_id');
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(42);
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_varchar');
        $attribute->method('getBackendType')->willReturn('varchar');
        $attribute->method('getIsGlobal')->willReturn(ScopedAttributeInterface::SCOPE_WEBSITE);
        $eavConfig = $this->createStub(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(7);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->expects(self::never())->method('getStores');

        $writer = new ProductAttributeWriter($resource, $productResource, $eavConfig, $storeManager);

        $this->expectException(LocalizedException::class);
        $writer->write(10, ['mapped_attribute' => [2 => 'first', 3 => 'second']]);
    }
}
