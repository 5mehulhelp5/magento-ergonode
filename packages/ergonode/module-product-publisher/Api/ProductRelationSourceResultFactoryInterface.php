<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;

interface ProductRelationSourceResultFactoryInterface
{
    /**
     * @param ProductRelationStateInterface $relations
     * @param array<string, bool> $authoritativeCollections
     * @return ProductRelationSourceResultInterface
     */
    public function create(
        ProductRelationStateInterface $relations,
        array $authoritativeCollections = []
    ): ProductRelationSourceResultInterface;
}
