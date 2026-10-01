<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Test\Unit\Model;

use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\CategoryAttributeConsumerAdminUi\Model\CategoryFormDataProviderPlugin;
use Magento\Catalog\Model\Category\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryFormDataProviderPluginTest extends TestCase
{
    public function testAddsReadOnlyMappingContextWithoutCreatingAnEavAttribute(): void
    {
        $contextProvider = $this->createMock(CategoryFormContextProviderInterface::class);
        $contextProvider->expects(self::once())->method('getForMagentoCategory')->with(12)->willReturn([
            'category_tree_id' => 7,
            'root_category_id' => 2,
            'ergonode_category_code' => 'chairs',
        ]);

        $result = (new CategoryFormDataProviderPlugin($contextProvider))->afterGetData(
            $this->createStub(DataProvider::class),
            [12 => ['entity_id' => 12, 'name' => 'Chairs']]
        );

        self::assertNotNull($result);
        self::assertSame('chairs', $result[12]['ergonode_category_code']);
        self::assertSame(7, $result[12]['ergonode_category_tree_id']);
        self::assertSame('Chairs', $result[12]['name']);
    }

    public function testLeavesVirtualValuesEmptyWithoutActiveTreeContext(): void
    {
        $contextProvider = $this->createStub(CategoryFormContextProviderInterface::class);
        $contextProvider->method('getForMagentoCategory')->willReturn(null);

        $result = (new CategoryFormDataProviderPlugin($contextProvider))->afterGetData(
            $this->createStub(DataProvider::class),
            [12 => ['id' => 12]]
        );

        self::assertNotNull($result);
        self::assertSame('', $result[12]['ergonode_category_code']);
        self::assertSame(0, $result[12]['ergonode_category_tree_id']);
    }

    public function testPreservesMissingCategoryData(): void
    {
        $contextProvider = $this->createMock(CategoryFormContextProviderInterface::class);
        $contextProvider->expects(self::never())->method('getForMagentoCategory');

        self::assertNull((new CategoryFormDataProviderPlugin($contextProvider))->afterGetData(
            $this->createStub(DataProvider::class),
            null
        ));
    }
}
