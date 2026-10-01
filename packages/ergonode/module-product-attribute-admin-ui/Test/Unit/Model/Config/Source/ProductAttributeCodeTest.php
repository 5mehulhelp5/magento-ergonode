<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Test\Unit\Model\Config\Source;

use ArrayIterator;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttributeAdminUi\Model\Config\Source\ProductAttributeCode;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use PHPUnit\Framework\TestCase;

class ProductAttributeCodeTest extends TestCase
{
    public function testListsHiddenMappableProductAttributesAndDisabledOption(): void
    {
        $defaultCategory = $this->attribute('default_category', 'Default Category');
        $excluded = $this->attribute('media_gallery', 'Media Gallery');
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('backend_type', ['neq' => 'static'])
            ->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$excluded, $defaultCategory]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $policy = $this->createStub(ProductAttributePolicy::class);
        $policy->method('isMappable')->willReturnCallback(
            static fn (string $code): bool => $code === 'default_category'
        );

        self::assertSame(
            [
            ['value' => '', 'label' => 'Disabled'],
            ['value' => 'default_category', 'label' => 'Default Category (default_category)'],
            ],
            (new ProductAttributeCode($collectionFactory, $policy))->toOptionArray()
        );
    }

    private function attribute(string $code, string $label): Attribute
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getDefaultFrontendLabel')->willReturn($label);

        return $attribute;
    }
}
