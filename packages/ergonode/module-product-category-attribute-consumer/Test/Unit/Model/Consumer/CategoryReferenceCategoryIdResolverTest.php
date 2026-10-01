<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Test\Unit\Model\Consumer;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductCategoryAttributeConsumer\Model\Consumer\CategoryReferenceCategoryIdResolver;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryReferenceCategoryIdResolverTest extends TestCase
{
    public function testResolvesOneCategoryWithinDefaultStoreRoot(): void
    {
        $mappingProvider = $this->createMock(CategoryMappingProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getMagentoCategoryIdsByErgonodeCodes')
            ->with(['chairs'], 2)
            ->willReturn(['chairs' => [42]]);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getStoreGroupId')->willReturn(3);
        $group = $this->createStub(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects(self::once())->method('getDefaultStoreView')->willReturn($store);
        $storeManager->expects(self::once())->method('getGroup')->with(3)->willReturn($group);

        self::assertSame(
            42,
            (new CategoryReferenceCategoryIdResolver($mappingProvider, $storeManager))->resolve('chairs', 0)
        );
    }
}
