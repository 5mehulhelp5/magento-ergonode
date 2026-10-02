<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\ResourceModel\FileAttributeWriter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileAttributeWriterTest extends TestCase
{
    #[DataProvider('inputs')]
    public function testWritesMediaUrlOnlyForTextTargets(string $input, string $expected): void
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getFrontendInput')->willReturn($input);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example/media/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $action = $this->createMock(Action::class);
        $action->expects(self::once())->method('updateAttributes')->with([23], ['manual' => $expected], 2);
        (new FileAttributeWriter($this->createStub(ResourceConnection::class), $this->createStub(Product::class),
            $eav, $action, $stores))->write(23, 'manual', 2, 'catalog/product/ergonode/shared/manual.pdf');
    }

    public static function inputs(): array
    {
        $path = 'catalog/product/ergonode/shared/manual.pdf';
        return ['file' => ['file', $path], 'text' => ['text', 'https://shop.example/media/' . $path],
            'textarea' => ['textarea', 'https://shop.example/media/' . $path]];
    }

    public function testClearsAllStoreValuesInWebsiteWithoutClearingDefaultValue(): void
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn(17);
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_varchar');
        $attribute->method('getBackendType')->willReturn('varchar');
        $attribute->method('getIsGlobal')->willReturn(2);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $website = $this->createStub(Website::class);
        $website->method('getStoreIds')->willReturn([2, 3]);
        $store = $this->createStub(Store::class);
        $store->method('getWebsite')->willReturn($website);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $products = $this->createStub(Product::class);
        $products->method('getLinkField')->willReturn('entity_id');
        $products->method('getIdFieldName')->willReturn('entity_id');
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with('catalog_product_entity_varchar', [
            'attribute_id = ?' => 17, 'entity_id = ?' => 23, 'store_id IN (?)' => [2, 3],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        (new FileAttributeWriter($resource, $products, $eav, $this->createStub(Action::class), $stores))
            ->clear(23, 'manual', 2);
    }
}
