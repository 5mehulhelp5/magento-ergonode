<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Model\GraphQl;

class ProductCategoryQueries
{
    public const string CATEGORIES = <<<'GRAPHQL'
query ConsumerProductCategories($sku: Sku!, $first: Int!, $after: String) {
  product(sku: $sku) { sku categoryList(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { code } }
  } }
}
GRAPHQL;
}
