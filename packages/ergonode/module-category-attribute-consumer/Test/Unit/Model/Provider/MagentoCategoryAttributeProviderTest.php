<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Provider;

use ArrayIterator;
use Ergonode\Attribute\Model\MagentoAttributeTypeResolver;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttribute\Model\Provider\MagentoCategoryAttributeProvider;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use PHPUnit\Framework\TestCase;

class MagentoCategoryAttributeProviderTest extends TestCase
{
    public function testIncludesMappableNativeAndCustomAttributesAndExcludesTechnicalOnes(): void
    {
        $attributes = [
            $this->attribute('name', 'Name', 'varchar', 'text', false, true),
            $this->attribute(
                'include_in_menu',
                'Include in Navigation Menu',
                'int',
                'select',
                false,
                true,
                Boolean::class
            ),
            $this->attribute('is_active', 'Is Active', 'int', 'select', sourceModel: Boolean::class),
            $this->attribute('default_sort_by', 'Default Product Listing Sort By', 'varchar', 'select'),
            $this->attribute('is_anchor', 'Is Anchor', 'int', 'select', sourceModel: Boolean::class),
            $this->attribute('custom_category_badge', 'Category Badge', 'varchar', 'text', true),
            $this->attribute('display_mode', 'Display Mode', 'varchar', 'select'),
            $this->attribute('meta_keywords', 'Meta Keywords', 'text', 'textarea'),
            $this->attribute('meta_title', 'Page Title', 'varchar', 'text'),
            $this->attribute('third_party_theme', 'Theme', 'varchar', 'select'),
            $this->attribute('path', 'Path', 'static', 'text'),
            $this->attribute('children', '', 'text', 'text'),
        ];
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new ArrayIterator($attributes));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $visibilityProvider = $this->createMock(MappingVisibilityProviderInterface::class);
        $visibilityProvider->expects(self::once())
            ->method('getActiveMap')
            ->with(
                'category_attribute',
                'magento',
                ['name', 'include_in_menu', 'is_active', 'custom_category_badge', 'meta_keywords', 'meta_title']
            )
            ->willReturn(['include_in_menu' => false, 'custom_category_badge' => false]);

        $policy = $this->createStub(CategoryAttributePolicy::class);
        $policy->method('isMappable')->willReturnCallback(
            static fn (string $code): bool => !in_array($code, ['default_sort_by', 'is_anchor'], true)
        );
        $policy->method('isRequiredMapping')->willReturnCallback(
            static fn (string $code, bool $nativeRequired): bool => $nativeRequired
                || in_array($code, ['include_in_menu', 'is_active'], true)
        );
        $provider = new MagentoCategoryAttributeProvider(
            $collectionFactory,
            $visibilityProvider,
            new MagentoAttributeTypeResolver(),
            $policy
        );

        self::assertSame(
            [
                ['label' => 'Name', 'code' => 'name', 'scope' => 'global', 'type' => 'text', 'active' => true,
                    'required' => true, 'has_custom_source' => false],
                ['label' => 'Include in Navigation Menu', 'code' => 'include_in_menu', 'scope' => 'global',
                    'type' => 'boolean', 'active' => true, 'required' => true, 'has_custom_source' => true],
                ['label' => 'Is Active', 'code' => 'is_active', 'scope' => 'global',
                    'type' => 'boolean', 'active' => true, 'required' => true, 'has_custom_source' => true],
                ['label' => 'Category Badge', 'code' => 'custom_category_badge', 'scope' => 'global',
                    'type' => 'text', 'active' => false, 'required' => false, 'has_custom_source' => false],
                ['label' => 'Meta Keywords', 'code' => 'meta_keywords', 'scope' => 'global', 'type' => 'textarea',
                    'active' => true, 'required' => false, 'has_custom_source' => false],
                ['label' => 'Page Title', 'code' => 'meta_title', 'scope' => 'global', 'type' => 'text',
                    'active' => true, 'required' => false, 'has_custom_source' => false],
            ],
            $provider->getAttributes()
        );
    }

    private function attribute(
        string $code,
        string $label,
        string $backendType,
        string $frontendInput,
        bool $isUserDefined = false,
        bool $isRequired = false,
        string $sourceModel = ''
    ): Attribute {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getDefaultFrontendLabel')->willReturn($label);
        $attribute->method('getBackendType')->willReturn($backendType);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);
        $attribute->method('getIsUserDefined')->willReturn($isUserDefined);
        $attribute->method('getIsRequired')->willReturn($isRequired);
        $attribute->method('getData')->willReturn(1);
        $attribute->method('getSourceModel')->willReturn($sourceModel);

        return $attribute;
    }
}
