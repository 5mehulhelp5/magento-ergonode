<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Import;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Import\SelectedProductImporter;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\MappedMagentoSkuResolver;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Port\SelectedProductWriterInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use PHPUnit\Framework\TestCase;

class SelectedProductImporterTest extends TestCase
{
    public function testMappedIdentityKeepsMagentoSkuAndWritesNativeErgonodeSku(): void
    {
        $loader = $this->createStub(RemoteProductLoader::class);
        $loader->method('load')->willReturn(new RemoteProduct('NAV-1', 'simple', 'template', false, [], []));
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('MAG-1');
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willReturn($product);
        $attributes = $this->createStub(AttributeManagementInterface::class);
        $attributes->method('getAttributes')->willReturn([]);
        $mapper = $this->createStub(ProductAttributeValueMapper::class);
        $mapper->method('map')->willReturn(['values' => [], 'clear' => []]);
        $resolver = $this->createMock(MappedMagentoSkuResolver::class);
        $resolver->expects(self::never())->method('resolve');
        $sku = $this->createMock(MagentoSkuSynchronizer::class);
        $sku->expects(self::once())->method('validate')->with(1, 'MAG-1', 'NAV-1');
        $sku->expects(self::once())->method('synchronizeIdentityAttribute')->with(
            1,
            'NAV-1',
            ProductIdentityInterface::MODE_MAPPED
        );
        $sku->expects(self::once())->method('synchronize')->with(1, 'MAG-1', 'NAV-1');
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getProductId')->willReturn(1);
        $identity->method('getErgonodeSku')->willReturn('NAV-1');
        $identity->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_MAPPED);

        (new SelectedProductImporter(
            $loader,
            $mapper,
            $this->createStub(SelectedProductWriterInterface::class),
            $products,
            $attributes,
            $resolver,
            $sku,
            $this->createStub(ProductIdentityServiceInterface::class),
            new \Ergonode\ProductConsumer\Model\Magento\SelectedProductStateSynchronizerPool()
        ))->import($identity);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('optionalStateFailure')]
    public function testMappedValuesAreLimitedToTargetSetAndIdentityIsValidatedBeforeWriting(bool $failMedia): void
    {
        $source = new RemoteProduct('ERGO-1', 'simple', 'foreign-template', false, [], []);
        $loader = $this->createStub(RemoteProductLoader::class);
        $loader->method('load')->willReturn($source);
        $product = $this->createStub(Product::class);
        $product->method('getAttributeSetId')->willReturn(4);
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willReturn($product);
        $attributes = $this->createStub(AttributeManagementInterface::class);
        $attributes->method('getAttributes')->willReturn(array_map(function (string $code): AttributeInterface {
            $attribute = $this->createStub(AttributeInterface::class);
            $attribute->method('getAttributeCode')->willReturn($code);
            return $attribute;
        }, ['name', 'sku', 'type_id', 'attribute_set_id', 'price']));
        $mapper = $this->createMock(ProductAttributeValueMapper::class);
        $mapper->expects(self::once())->method('map')->with([], ['name', 'price'])
            ->willReturn(['values' => ['name' => [0 => 'Imported']], 'clear' => []]);
        $resolver = $this->createStub(MappedMagentoSkuResolver::class);
        $resolver->method('resolve')->willReturn('MAG-1');
        $validated = false;
        $sku = $this->createMock(MagentoSkuSynchronizer::class);
        $sku->expects(self::once())->method('validate')->with(1, 'MAG-1', 'ERGO-1')
            ->willReturnCallback(static function () use (&$validated): void {
                $validated = true;
            });
        $sku->expects(self::once())->method('synchronize')->with(1, 'MAG-1', 'ERGO-1');
        $writer = $this->createMock(SelectedProductWriterInterface::class);
        $writer->expects(self::once())->method('write')->with(1, ['name' => [0 => 'Imported']], [])
            ->willReturnCallback(static function () use (&$validated): void {
                self::assertTrue($validated);
            });
        $identities = $this->createMock(ProductIdentityServiceInterface::class);
        $synchronized = false;
        $optional = $this->createMock(\Ergonode\ProductConsumer\Api\SelectedProductStateSynchronizerInterface::class);
        $optional->expects(self::once())->method('synchronizeSelected')->with(1, 'MAG-1', $source, ['name', 'price'])
            ->willReturnCallback(static function () use (&$synchronized, $failMedia): void {
                if ($failMedia) { throw new \RuntimeException('Media scheduling failed'); }
                $synchronized = true;
            });
        $identities->expects($failMedia ? self::never() : self::once())->method('recordImported')->with(1, 'ERGO-1', self::isString())
            ->willReturnCallback(static function () use (&$synchronized): void { self::assertTrue($synchronized); });
        $identity = $this->createStub(ProductIdentityInterface::class);
        $identity->method('getProductId')->willReturn(1);
        $identity->method('getErgonodeSku')->willReturn('ERGO-1');
        $identity->method('getIdentityMode')->willReturn('assigned');
        if ($failMedia) {
            $this->expectExceptionMessage('Media scheduling failed');
        }
        (new SelectedProductImporter(
            $loader,
            $mapper,
            $writer,
            $products,
            $attributes,
            $resolver,
            $sku,
            $identities,
            new \Ergonode\ProductConsumer\Model\Magento\SelectedProductStateSynchronizerPool([$optional])
        ))
            ->import($identity);
    }

    public static function optionalStateFailure(): array
    {
        return ['media scheduled before success' => [false], 'media failure must not record success' => [true]];
    }
}
