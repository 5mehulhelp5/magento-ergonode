<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Ui\DataProvider\Product;

use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Data\Collection;
use Magento\Ui\DataProvider\AddFieldToCollectionInterface;

class AddErgonodeSkuFieldToCollection implements AddFieldToCollectionInterface
{
    public function addField(Collection $collection, $field, $alias = null): void
    {
        if (!$collection instanceof ProductCollection) {
            throw new InvalidArgumentException('Ergonode SKU can only be joined to a product collection.');
        }

        $collection->joinField(
            'ergonode_sku',
            'ergonode_product_mapping',
            'ergonode_sku',
            'product_id=entity_id',
            null,
            'left'
        );
    }
}
