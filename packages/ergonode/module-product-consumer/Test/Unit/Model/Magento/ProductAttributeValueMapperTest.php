<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Api\ProductAttributeMappingDeferrerInterface;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeMappingDeferrerPool;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueResolverPool;
use Ergonode\ProductConsumer\Test\Unit\Support\RemoteProductAttributeFixture;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use PackHauer\FileAttribute\Api\FileStorageInterface;

class ProductAttributeValueMapperTest extends TestCase
{
    public function testMapsNativeStatusAndVisibilityOptions(): void
    {
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([
            [
                'mapping_id' => 1,
                'ergonode_attribute_code' => 'status',
                'magento_attribute_code' => 'status',
                'ergonode_type' => 'select',
                'magento_type' => 'select',
                'option_ids' => ['enabled' => 1, 'disabled' => 2],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
                'magento_has_custom_source' => true,
                'magento_source_option_values' => [1 => 1, 2 => 2],
            ],
            [
                'mapping_id' => 2,
                'ergonode_attribute_code' => 'is_active',
                'magento_attribute_code' => 'visibility',
                'ergonode_type' => 'select',
                'magento_type' => 'select',
                'option_ids' => ['catalog_search' => 4],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
                'magento_has_custom_source' => true,
                'magento_source_option_values' => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
            ],
        ]);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);

        $mapper = new ProductAttributeValueMapper(
            $mappingProvider,
            $languageMappingProvider,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            $this->createStub(FileStorageInterface::class),
            new ProductAttributeValueResolverPool(),
            new ProductAttributeMappingDeferrerPool()
        );

        self::assertSame([
            'values' => [
                'status' => [0 => '1'],
                'visibility' => [0 => '4'],
            ],
            'clear' => [],
        ], $mapper->map([
            RemoteProductAttributeFixture::string('status', 'select', ['en_GB' => 'enabled']),
            RemoteProductAttributeFixture::string('is_active', 'select', ['en_GB' => 'catalog_search']),
        ]));
        self::assertSame(['values' => [], 'clear' => []], $mapper->map([], ['name']));
    }

    public function testDefersMappingOwnedByOptionalExtension(): void
    {
        $mapping = [
            'mapping_id' => 1,
            'ergonode_attribute_code' => 'instruction',
            'magento_attribute_code' => 'instruction_file',
            'ergonode_type' => 'file',
            'magento_type' => 'file',
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
            'magento_has_custom_source' => false,
            'magento_source_option_values' => [],
        ];
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([$mapping]);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $fileStorage = $this->createMock(FileStorageInterface::class);
        $fileStorage->expects(self::never())->method('getPermanentDirectory');
        $fileDownloader = $this->createMock(ErgonodeFileDownloaderInterface::class);
        $fileDownloader->expects(self::never())->method('download');
        $deferrer = $this->createMock(ProductAttributeMappingDeferrerInterface::class);
        $deferrer->expects(self::once())->method('supports')->with($mapping)->willReturn(true);

        $mapper = new ProductAttributeValueMapper(
            $mappingProvider,
            $languageMappingProvider,
            $fileDownloader,
            $fileStorage,
            new ProductAttributeValueResolverPool(),
            new ProductAttributeMappingDeferrerPool([$deferrer])
        );

        self::assertSame(['values' => [], 'clear' => []], $mapper->map([
            RemoteProductAttributeFixture::string('instruction', 'file', ['pl_PL' => 'instruction.pdf']),
        ]));
    }

    public function testSynchronizesFileMappingWhenNoExtensionClaimsIt(): void
    {
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([[
            'mapping_id' => 1,
            'ergonode_attribute_code' => 'instruction',
            'magento_attribute_code' => 'instruction_file',
            'ergonode_type' => 'file',
            'magento_type' => 'file',
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
            'magento_has_custom_source' => false,
            'magento_source_option_values' => [],
        ]]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::once())
            ->method('getPermanentDirectory')
            ->with('instruction_file')
            ->willReturn('catalog/product/files/instruction_file');
        $downloader = $this->createMock(ErgonodeFileDownloaderInterface::class);
        $downloader->expects(self::once())
            ->method('download')
            ->with('instruction.pdf', 'catalog/product/files/instruction_file')
            ->willReturn(['relative_path' => 'files/instruction.pdf', 'media_url' => '/media/instruction.pdf']);

        $mapper = new ProductAttributeValueMapper(
            $mappingProvider,
            $languages,
            $downloader,
            $storage,
            new ProductAttributeValueResolverPool(),
            new ProductAttributeMappingDeferrerPool()
        );

        self::assertSame([
            'values' => ['instruction_file' => [0 => 'files/instruction.pdf']],
            'clear' => [],
        ], $mapper->map([
            RemoteProductAttributeFixture::string('instruction', 'file', ['pl_PL' => 'instruction.pdf']),
        ]));
    }

    public function testRejectsNegativeMappedProductPrice(): void
    {
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([[
            'mapping_id' => 1,
            'ergonode_attribute_code' => 'regular_price',
            'magento_attribute_code' => 'price',
            'ergonode_type' => 'price',
            'magento_type' => 'price',
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
            'magento_has_custom_source' => false,
            'magento_source_option_values' => [],
        ]]);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $mapper = new ProductAttributeValueMapper(
            $mappingProvider,
            $languageMappingProvider,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            $this->createStub(FileStorageInterface::class),
            new ProductAttributeValueResolverPool(),
            new ProductAttributeMappingDeferrerPool()
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Mapped product price must be a non-negative decimal number.');

        $mapper->map([
            RemoteProductAttributeFixture::number('regular_price', 'price', ['pl_PL' => -0.01]),
        ]);
    }

    public function testRejectsMappedProductNameLongerThanMagentoStorage(): void
    {
        $mappingProvider = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappingProvider->method('getMappings')->willReturn([[
            'mapping_id' => 1,
            'ergonode_attribute_code' => 'product_name',
            'magento_attribute_code' => 'name',
            'ergonode_type' => 'text',
            'magento_type' => 'text',
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => [],
            'magento_has_custom_source' => false,
            'magento_source_option_values' => [],
        ]]);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'pl_PL']);
        $mapper = new ProductAttributeValueMapper(
            $mappingProvider,
            $languageMappingProvider,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            $this->createStub(FileStorageInterface::class),
            new ProductAttributeValueResolverPool(),
            new ProductAttributeMappingDeferrerPool()
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Mapped product name must not exceed 255 characters.');

        $mapper->map([
            RemoteProductAttributeFixture::string('product_name', 'text', ['pl_PL' => str_repeat('ą', 256)]),
        ]);
    }
}
