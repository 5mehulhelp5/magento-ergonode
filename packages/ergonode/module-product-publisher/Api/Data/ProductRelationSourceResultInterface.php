<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

interface ProductRelationSourceResultInterface
{
    /**
     * @return ProductRelationStateInterface
     */
    public function getRelations(): ProductRelationStateInterface;

    /**
     * @return array<string, bool>
     */
    public function getAuthoritativeCollections(): array;
}
