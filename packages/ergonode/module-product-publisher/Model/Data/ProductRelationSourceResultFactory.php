<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use Ergonode\ProductPublisher\Api\ProductRelationSourceResultFactoryInterface;

class ProductRelationSourceResultFactory implements ProductRelationSourceResultFactoryInterface
{
    public function create(
        ProductRelationStateInterface $relations,
        array $authoritativeCollections = []
    ): ProductRelationSourceResultInterface {
        return new ProductRelationSourceResult($relations, $authoritativeCollections);
    }
}
