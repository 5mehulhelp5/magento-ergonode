<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Test\Unit\Ui\DataProvider\Product;

use Ergonode\ProductAdminUi\Ui\DataProvider\Product\AddErgonodeSkuFieldToCollection;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Data\Collection;
use PHPUnit\Framework\TestCase;

class AddErgonodeSkuFieldToCollectionTest extends TestCase
{
    public function testLeftJoinsSharedProductIdentityMapping(): void
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->expects(self::once())
            ->method('joinField')
            ->with(
                'ergonode_sku',
                'ergonode_product_mapping',
                'ergonode_sku',
                'product_id=entity_id',
                null,
                'left'
            )
            ->willReturnSelf();

        (new AddErgonodeSkuFieldToCollection())->addField($collection, 'ergonode_sku');
    }

    public function testRejectsAnUnrelatedCollection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AddErgonodeSkuFieldToCollection())->addField(
            $this->createStub(Collection::class),
            'ergonode_sku'
        );
    }
}
