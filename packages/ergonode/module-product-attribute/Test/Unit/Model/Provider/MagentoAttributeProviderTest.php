<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Test\Unit\Model\Provider;

use ArrayIterator;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Model\Provider\VisibilityProvider;
use Ergonode\Attribute\Model\MagentoAttributeTypeResolver;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeMetadataContributorPool;
use Ergonode\ProductAttribute\Api\MagentoAttributeMetadataContributorInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;

class MagentoAttributeProviderTest extends TestCase
{
    public function testMarksRequiredCustomSourceAndKeepsItActive(): void
    {
        $source = $this->createStub(AbstractSource::class);
        $source->method('getAllOptions')->willReturn(
            [
            ['label' => 'Not Visible Individually', 'value' => 1],
            ['label' => 'Catalog', 'value' => 2],
            ['label' => 'Search', 'value' => 3],
            ['label' => 'Catalog, Search', 'value' => 4],
            ]
        );
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn('visibility');
        $attribute->method('getDefaultFrontendLabel')->willReturn('Visibility');
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getBackendType')->willReturn('int');
        $attribute->method('getSourceModel')->willReturn(Visibility::class);
        $attribute->method('getSource')->willReturn($source);
        $attribute->method('getIsUserDefined')->willReturn(false);
        $attribute->method('getIsRequired')->willReturn(true);
        $attribute->method('isScopeGlobal')->willReturn(true);

        $collection = $this->createStub(Collection::class);
        $collection->method('addVisibleFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$attribute]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $visibilityProvider = $this->createStub(VisibilityProvider::class);
        $visibilityProvider->method('getActiveMap')->willReturn(['visibility' => false]);
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->method('isMappable')->willReturn(true);

        $provider = new MagentoAttributeProvider(
            $collectionFactory,
            $visibilityProvider,
            new MagentoAttributeTypeResolver(),
            $attributePolicy,
            new MagentoAttributeMetadataContributorPool()
        );
        $metadata = $provider->getAttribute('visibility');

        self::assertNotNull($metadata);
        self::assertTrue($metadata['has_custom_source']);
        self::assertTrue($metadata['required']);
        self::assertTrue($metadata['active']);
        self::assertSame(['1', '2', '3', '4'], $provider->getCustomSourceOptionValues('visibility'));
    }

    public function testUsesSourceModelToDistinguishBooleanFromStatusSelect(): void
    {
        $booleanAttribute = $this->attribute('is_enabled', Boolean::class, true);
        $statusAttribute = $this->attribute('status', Status::class, false);
        $collection = $this->createStub(Collection::class);
        $collection->method('addVisibleFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$booleanAttribute, $statusAttribute]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $visibilityProvider = $this->createStub(VisibilityProvider::class);
        $visibilityProvider->method('getActiveMap')->willReturn([]);
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->method('isMappable')->willReturn(true);
        $provider = new MagentoAttributeProvider(
            $collectionFactory,
            $visibilityProvider,
            new MagentoAttributeTypeResolver(),
            $attributePolicy,
            new MagentoAttributeMetadataContributorPool()
        );

        self::assertSame('boolean', $provider->getAttribute('is_enabled')['type'] ?? null);
        self::assertSame('select', $provider->getAttribute('status')['type'] ?? null);
    }

    public function testConfiguredMappingTargetRemainsRequiredAndActiveWhenEavAttributeIsOptional(): void
    {
        $attribute = $this->attribute('status', Status::class, false);
        $collection = $this->createStub(Collection::class);
        $collection->method('addVisibleFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$attribute]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $visibilityProvider = $this->createStub(VisibilityProvider::class);
        $visibilityProvider->method('getActiveMap')->willReturn(['status' => false]);
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->method('isMappable')->willReturn(true);
        $attributePolicy->method('isMappingRequired')->willReturn(true);
        $provider = new MagentoAttributeProvider(
            $collectionFactory,
            $visibilityProvider,
            new MagentoAttributeTypeResolver(),
            $attributePolicy,
            new MagentoAttributeMetadataContributorPool()
        );

        $metadata = $provider->getAttribute('status');

        self::assertNotNull($metadata);
        self::assertTrue($metadata['required']);
        self::assertTrue($metadata['active']);
    }

    public function testMetadataContributorCanIncludeAndDecorateHiddenAttribute(): void
    {
        $visibleCollection = $this->createStub(Collection::class);
        $visibleCollection->method('addVisibleFilter')->willReturnSelf();
        $visibleCollection->method('getIterator')->willReturn(new ArrayIterator([]));
        $hiddenAttribute = $this->createStub(Attribute::class);
        $hiddenAttribute->method('getAttributeCode')->willReturn('extension_owned');
        $hiddenAttribute->method('getDefaultFrontendLabel')->willReturn('Extension Owned');
        $hiddenAttribute->method('getFrontendInput')->willReturn('text');
        $hiddenAttribute->method('getBackendType')->willReturn('varchar');
        $hiddenAttribute->method('getSourceModel')->willReturn('');
        $hiddenAttribute->method('getIsUserDefined')->willReturn(true);
        $hiddenAttribute->method('getIsRequired')->willReturn(true);
        $hiddenAttribute->method('isScopeGlobal')->willReturn(false);
        $hiddenAttribute->method('isScopeWebsite')->willReturn(false);
        $hiddenCollection = $this->createMock(Collection::class);
        $hiddenCollection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('attribute_code', ['in' => ['extension_owned']])
            ->willReturnSelf();
        $hiddenCollection->method('getIterator')->willReturn(new ArrayIterator([$hiddenAttribute]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturnOnConsecutiveCalls($visibleCollection, $hiddenCollection);
        $attributePolicy = $this->createStub(ProductAttributePolicy::class);
        $attributePolicy->method('isTypeMappable')->willReturn(true);
        $attributePolicy->method('isMappable')->willReturn(true);
        $contributor = $this->createStub(MagentoAttributeMetadataContributorInterface::class);
        $contributor->method('getAdditionalAttributeCodes')->willReturn(['extension_owned']);
        $contributor->method('contribute')->willReturnCallback(
            static function (array $metadata): array {
                $metadata['extension_marker'] = true;

                return $metadata;
            }
        );

        $provider = new MagentoAttributeProvider(
            $collectionFactory,
            $this->createStub(VisibilityProvider::class),
            new MagentoAttributeTypeResolver(),
            $attributePolicy,
            new MagentoAttributeMetadataContributorPool([$contributor])
        );

        self::assertSame('extension_owned', $provider->getAttributes()[0]['code'] ?? null);
        self::assertSame('text', $provider->getAttributes()[0]['type'] ?? null);
        self::assertTrue($provider->getAttributes()[0]['extension_marker'] ?? false);
    }

    private function attribute(string $code, string $sourceModel, bool $isUserDefined): Attribute
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getDefaultFrontendLabel')->willReturn($code);
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('getBackendType')->willReturn('int');
        $attribute->method('getSourceModel')->willReturn($sourceModel);
        $attribute->method('getIsUserDefined')->willReturn($isUserDefined);
        $attribute->method('getIsRequired')->willReturn(false);
        $attribute->method('isScopeGlobal')->willReturn(true);

        return $attribute;
    }
}
