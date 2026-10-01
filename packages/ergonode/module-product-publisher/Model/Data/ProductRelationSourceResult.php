<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductCollectionCompletenessInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationSourceResultInterface;
use Ergonode\ProductPublisher\Api\Data\ProductRelationStateInterface;
use InvalidArgumentException;

final readonly class ProductRelationSourceResult implements ProductRelationSourceResultInterface
{
    /** @var array<string, bool> */
    private array $authoritativeCollections;

    /** @param array<string, bool> $authoritativeCollections */
    public function __construct(
        private ProductRelationStateInterface $relations,
        array $authoritativeCollections = []
    ) {
        $allowed = [
            ProductCollectionCompletenessInterface::COLLECTION_BINDINGS,
            ProductCollectionCompletenessInterface::COLLECTION_VARIANTS,
            ProductCollectionCompletenessInterface::COLLECTION_GROUPED_CHILDREN,
        ];
        foreach ($authoritativeCollections as $collection => $authoritative) {
            if (!in_array($collection, $allowed, true) || !is_bool($authoritative)) {
                throw new InvalidArgumentException('Product relation completeness contains an invalid collection.');
            }
        }
        $this->authoritativeCollections = $authoritativeCollections;
    }

    public function getRelations(): ProductRelationStateInterface
    {
        return $this->relations;
    }

    public function getAuthoritativeCollections(): array
    {
        return $this->authoritativeCollections;
    }
}
