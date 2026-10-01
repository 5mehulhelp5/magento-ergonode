<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Test\Unit\Model\Consumer;

use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;
use Ergonode\ProductCategoryAttributeConsumer\Model\Consumer\CategoryReferenceCategoryIdResolver;
use Ergonode\ProductCategoryAttributeConsumer\Model\Consumer\CategoryReferenceConsumerValueResolver;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeMappingDeferrerPool;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueMapper;
use Ergonode\ProductConsumer\Model\Magento\ProductAttributeValueResolverPool;
use Ergonode\ProductConsumer\Test\Unit\Support\RemoteProductAttributeFixture;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PackHauer\FileAttribute\Api\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class CategoryReferenceMappingOperationTest extends TestCase
{
    public function testReusesCategoryAcrossStoresAndWriteButRefreshesNextOperation(): void
    {
        $categories = $this->createMock(CategoryMappingProviderInterface::class);
        $categories->expects(self::exactly(2))->method('getMagentoCategoryIdsByErgonodeCodes')
            ->with(['chairs'], 2)->willReturnOnConsecutiveCalls(['chairs' => [42]], ['chairs' => [87]]);
        $mapper = $this->mapper($categories, [0 => 2, 1 => 2]);
        $attributes = [RemoteProductAttributeFixture::string('category', 'text', ['en_GB' => 'chairs'])];

        $special = $mapper->mapSpecial($attributes);
        self::assertSame([0 => 42, 1 => 42], $special['values']['default_category']);
        self::assertSame($special, $mapper->map($attributes, null, $special));
        self::assertSame([0 => 87, 1 => 87], $mapper->mapSpecial($attributes)['values']['default_category']);
    }

    public function testDoesNotShareCategoryIdsBetweenDifferentRoots(): void
    {
        $categories = $this->createMock(CategoryMappingProviderInterface::class);
        $categories->expects(self::exactly(2))->method('getMagentoCategoryIdsByErgonodeCodes')
            ->willReturnCallback(static fn (array $codes, int $root): array => [$codes[0] => [$root * 10]]);
        $mapper = $this->mapper($categories, [0 => 2, 1 => 3]);

        self::assertSame([0 => 20, 1 => 30], $mapper->mapSpecial([
            RemoteProductAttributeFixture::string('category', 'text', ['en_GB' => 'chairs']),
        ])['values']['default_category']);
    }

    public function testReusesExplicitClearAndHonorsSelectedAttributeScope(): void
    {
        $categories = $this->createMock(CategoryMappingProviderInterface::class);
        $categories->expects(self::never())->method('getMagentoCategoryIdsByErgonodeCodes');
        $mapper = $this->mapper($categories, [0 => 2, 1 => 2]);
        $special = $mapper->mapSpecial([]);
        self::assertSame(['values' => [], 'clear' => ['default_category' => [0, 1]]], $special);
        // Even a different input must not overwrite a result explicitly supplied by the same import operation.
        $attributes = [RemoteProductAttributeFixture::string('category', 'text', ['en_GB' => 'chairs'])];
        self::assertSame($special, $mapper->map($attributes, null, $special));
        self::assertSame(['values' => [], 'clear' => []], $mapper->map($attributes, ['name'], $special));
    }

    public function testFailedOperationDoesNotRetainPreviouslyResolvedCategories(): void
    {
        $categories = $this->createMock(CategoryMappingProviderInterface::class);
        $call = 0;
        $categories->expects(self::exactly(4))->method('getMagentoCategoryIdsByErgonodeCodes')
            ->willReturnCallback(static function (array $codes, int $root) use (&$call): array {
                ++$call;
                if ($call === 2) {
                    throw new LocalizedException(__('Mapping temporarily unavailable.'));
                }

                return [$codes[0] => [$call === 1 ? 42 : $root * 100]];
            });
        $mapper = $this->mapper($categories, [0 => 2, 1 => 3]);
        $attributes = [RemoteProductAttributeFixture::string('category', 'text', ['en_GB' => 'chairs'])];
        try {
            $mapper->mapSpecial($attributes);
            self::fail('Expected a failed mapping operation.');
        } catch (LocalizedException $exception) {
            self::assertSame('Mapping temporarily unavailable.', $exception->getMessage());
        }
        self::assertSame([0 => 200, 1 => 300], $mapper->mapSpecial($attributes)['values']['default_category']);
    }

    /** @param array<int, int> $roots */
    private function mapper(CategoryMappingProviderInterface $categories, array $roots): ProductAttributeValueMapper
    {
        $stores = [];
        $groups = [];
        foreach ($roots as $storeId => $rootId) {
            $stores[$storeId] = $this->createStub(StoreInterface::class);
            $stores[$storeId]->method('getStoreGroupId')->willReturn($rootId);
            $groups[$rootId] = $this->createStub(GroupInterface::class);
            $groups[$rootId]->method('getRootCategoryId')->willReturn($rootId);
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn($stores[0]);
        $storeManager->method('getStore')->willReturnCallback(static fn (int $id): StoreInterface => $stores[$id]);
        $storeManager->method('getGroup')->willReturnCallback(static fn (int $id): GroupInterface => $groups[$id]);
        $config = $this->createStub(CategoryReferenceAttributeConfigInterface::class);
        $config->method('isConfigured')->willReturn(true);
        $resolver = new CategoryReferenceConsumerValueResolver(
            $config,
            new CategoryReferenceCategoryIdResolver($categories, $storeManager)
        );
        $mappings = $this->createStub(ProductAttributeMappingProviderInterface::class);
        $mappings->method('getMappings')->willReturn([[
            'magento_attribute_code' => 'default_category',
            'ergonode_attribute_code' => 'category',
            'ergonode_type' => 'text',
        ]]);
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageStoreMap')->willReturn(array_fill_keys(array_keys($roots), 'en_GB'));

        return new ProductAttributeValueMapper(
            $mappings,
            $languages,
            $this->createStub(ErgonodeFileDownloaderInterface::class),
            $this->createStub(FileStorageInterface::class),
            new ProductAttributeValueResolverPool([$resolver]),
            new ProductAttributeMappingDeferrerPool()
        );
    }
}
