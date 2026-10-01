<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Unit\Model;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductCategoryConsumer\Model\CategoryIdsResolver;
use PHPUnit\Framework\TestCase;

class CategoryIdsResolverTest extends TestCase
{
    public function testKeepsExistingMultiTreeCategoryAssignmentBehavior(): void
    {
        $mappingProvider = $this->createMock(CategoryMappingProviderInterface::class);
        $mappingProvider->expects(self::once())
            ->method('getMagentoCategoryIdsByErgonodeCodes')
            ->with(['tables', 'chairs'])
            ->willReturn(['chairs' => [42, 142], 'tables' => [43]]);

        self::assertSame(
            [42, 43, 142],
            (new CategoryIdsResolver($mappingProvider))->resolve(['tables', 'chairs'])
        );
    }
}
