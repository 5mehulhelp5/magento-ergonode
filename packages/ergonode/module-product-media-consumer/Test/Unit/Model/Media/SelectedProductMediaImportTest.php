<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Media\Api\FileUsageRecorderInterface;
use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductConsumer\Api\ProductImportReadinessInterface;
use Ergonode\ProductConsumer\Model\GraphQl\RemoteProductLoader;
use Ergonode\ProductConsumer\Model\Import\ProductImportBatch;
use Ergonode\ProductConsumer\Model\Import\SelectedProductImporter;
use Ergonode\ProductConsumer\Model\Magento\MagentoSkuSynchronizer;
use Ergonode\ProductConsumer\Model\Magento\MappedMagentoSkuResolver;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\SelectedProductStateSynchronizerPool;
use Ergonode\ProductConsumer\Model\Port\SelectedProductWriterInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttributeType;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMediaConsumer\Model\Magento\AsynchronousFileAttributeMapping;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductMediaSynchronizer;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SelectedProductMediaImportTest extends TestCase
{
    #[DataProvider('batchSizes')]
    public function testAdminSingleAndBatchImportsReachCanonicalMediaProcessWithTargetAttributeScope(int $size): void
    {
        $source = new RemoteProduct('ERGO-1', 'simple', 'template', false, [], [
            new RemoteProductAttribute('manual', new RemoteProductAttributeType('file'),
                new LocalizedStringValues(['pl_PL' => 'manual.pdf'])),
            new RemoteProductAttribute('other', new RemoteProductAttributeType('file'),
                new LocalizedStringValues(['pl_PL' => 'outside-target-set.pdf'])),
        ]);
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([
            ['ergonode_attribute_code' => 'manual', 'magento_attribute_code' => 'manual_url',
                'ergonode_type' => 'file', 'magento_type' => 'text'],
            ['ergonode_attribute_code' => 'other', 'magento_attribute_code' => 'other_file',
                'ergonode_type' => 'file', 'magento_type' => 'file'],
        ]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getIsGlobal')->willReturn(1);
        $eav = $this->createStub(Config::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $sets = [];
        $recorder = $this->createMock(FileUsageRecorderInterface::class);
        $recorder->expects(self::exactly($size))->method('synchronize')->willReturnCallback(
            static function (int $id, FileUsageSet $set) use (&$sets): void { $sets[$id] = $set; }
        );
        $files = new ProductFileUsageSynchronizer($mappings, $languages, $recorder, $eav,
            new AsynchronousFileAttributeMapping(), $this->createStub(StoreManagerInterface::class),
            $this->createStub(ImageRolesInterface::class));
        $selection = new GallerySelection(['photo.jpg']);
        $provider = $this->createStub(ProductGallerySelectionProvider::class);
        $provider->method('provide')->willReturn($selection);
        $scheduled = [];
        $gallery = $this->createMock(GallerySchedulerInterface::class);
        $gallery->expects(self::exactly($size))->method('schedule')->willReturnCallback(
            static function (int $id, GallerySelection $value) use (&$scheduled): void { $scheduled[$id] = $value->paths(); }
        );
        $media = new ProductMediaSynchronizer($files, $provider, $gallery, $this->createStub(MediaRepositoryInterface::class));
        $loader = $this->createStub(RemoteProductLoader::class);
        $loader->method('load')->willReturn($source);
        $mapper = $this->createStub(ProductAttributeValueMapper::class);
        $mapper->method('map')->willReturn(['values' => [], 'clear' => []]);
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn('MAG-1');
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willReturn($product);
        $target = $this->createStub(AttributeInterface::class);
        $target->method('getAttributeCode')->willReturn('manual_url');
        $attributes = $this->createStub(AttributeManagementInterface::class);
        $attributes->method('getAttributes')->willReturn([$target]);
        $identities = [];
        foreach (range(1, $size) as $id) {
            $identity = $this->createStub(ProductIdentityInterface::class);
            $identity->method('getProductId')->willReturn($id);
            $identity->method('getErgonodeSku')->willReturn('ERGO-1');
            $identity->method('getIdentityMode')->willReturn(ProductIdentityInterface::MODE_MAPPED);
            $identities[$id] = $identity;
        }
        $service = $this->createMock(ProductIdentityServiceInterface::class);
        $service->method('getIdentitiesByProductIds')->willReturn($identities);
        $service->expects(self::exactly($size))->method('recordImported');
        $importer = new SelectedProductImporter($loader, $mapper, $this->createStub(SelectedProductWriterInterface::class),
            $products, $attributes, $this->createStub(MappedMagentoSkuResolver::class),
            $this->createStub(MagentoSkuSynchronizer::class), $service, new SelectedProductStateSynchronizerPool([$media]));
        $readiness = $this->createStub(ProductImportReadinessInterface::class);
        $readiness->method('getStatus')->willReturn(['ready' => true, 'message' => '']);
        $results = (new ProductImportBatch($this->createStub(ProductAttributeSourcePreparationInterface::class),
            $readiness, $service, $importer, new NullLogger()))->import(range(1, $size));
        self::assertSame(array_fill(0, $size, 'success'), array_column($results, 'status'));
        self::assertCount($size, $sets);
        foreach ($sets as $id => $set) {
            self::assertSame([['source_path' => 'manual.pdf', 'attribute_code' => 'manual_url', 'store_id' => 0]], $set->toRows());
            self::assertSame(['manual_url'], $set->attributeCodes());
            self::assertSame(['photo.jpg'], $scheduled[$id]);
        }
    }

    public static function batchSizes(): array
    {
        return ['one selected product' => [1], 'maximum Admin batch' => [50]];
    }
}
