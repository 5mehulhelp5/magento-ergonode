<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Ui\DataProvider\Product;

use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Data\Collection;
use Magento\Ui\DataProvider\AddFilterToCollectionInterface;

class AddErgonodeSkuFilterToCollection implements AddFilterToCollectionInterface
{
    public function addFilter(Collection $collection, $field, $condition = null): void
    {
        if (!$collection instanceof ProductCollection) {
            throw new InvalidArgumentException('Ergonode SKU can only filter a product collection.');
        }

        $collection->addFieldToFilter('ergonode_sku', $condition);
    }
}
