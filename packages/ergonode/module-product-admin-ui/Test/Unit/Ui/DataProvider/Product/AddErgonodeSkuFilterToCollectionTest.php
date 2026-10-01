<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Ui\DataProvider\Product;

use Ergonode\ProductAdminUi\Ui\DataProvider\Product\AddErgonodeSkuFilterToCollection;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Data\Collection;
use PHPUnit\Framework\TestCase;

class AddErgonodeSkuFilterToCollectionTest extends TestCase
{
    public function testAppliesTextConditionToJoinedErgonodeSkuField(): void
    {
        $condition = ['like' => '%ERG-123%'];
        $collection = $this->createMock(ProductCollection::class);
        $collection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('ergonode_sku', $condition)
            ->willReturnSelf();

        (new AddErgonodeSkuFilterToCollection())->addFilter($collection, 'ergonode_sku', $condition);
    }

    public function testRejectsAnUnrelatedCollection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AddErgonodeSkuFilterToCollection())->addFilter(
            $this->createStub(Collection::class),
            'ergonode_sku',
            ['like' => '%ERG%']
        );
    }
}
