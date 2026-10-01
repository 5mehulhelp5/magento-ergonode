<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

class ProductQueries
{
    public const string PRODUCT_STREAM = <<<'GRAPHQL'
query ConsumerProductStream(
  $first: Int!, $after: String, $languages: [Language!], $attributeCodes: [AttributeCode!]
) {
  productStream(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      cursor
      node {
        __typename
        sku
        createdAt
        editedAt
        template { code }
        status(languages: $languages) { language value { code } }
        attributeList(first: 100, codes: $attributeCodes) {
          pageInfo { hasNextPage endCursor }
          edges { node {
            __typename
            attribute { code }
            ... on DateAttributeValue { translations(languages: $languages) { language value } }
            ... on FileAttributeValue { translations(languages: $languages) { language value { path } } }
            ... on GalleryAttributeValue { translations(languages: $languages) { language value { path } } }
            ... on ImageAttributeValue { translations(languages: $languages) { language value { path } } }
            ... on MultiSelectAttributeValue { translations(languages: $languages) { language value { code } } }
            ... on NumberAttributeValue { translations(languages: $languages) { language value } }
            ... on PriceAttributeValue { translations(languages: $languages) { language value } }
            ... on ProductRelationAttributeValue { translations(languages: $languages) { language value { sku } } }
            ... on SelectAttributeValue { translations(languages: $languages) { language value { code } } }
            ... on TextAttributeValue { translations(languages: $languages) { language value } }
            ... on TextareaAttributeValue { translations(languages: $languages) { language rawValue } }
            ... on UnitAttributeValue { translations(languages: $languages) { language value } }
          } }
        }
        ... on SimpleProduct { isVariant }
        ... on VariableProduct {
          bindings { code }
          variantList(first: 100) {
            pageInfo { hasNextPage endCursor }
            edges { node { sku } }
          }
        }
        ... on GroupingProduct {
          childrenList(first: 100) {
            pageInfo { hasNextPage endCursor }
            edges { node { quantity product {
              ... on SimpleProduct { sku }
              ... on VariableProduct { sku }
            } } }
          }
        }
      }
    }
  }
}
GRAPHQL;

    public const string PRODUCT_DELETED_STREAM = <<<'GRAPHQL'
query ConsumerProductDeletedStream($first: Int!, $after: String) {
  productDeletedStream(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { cursor node }
  }
}
GRAPHQL;

    public const string PRODUCT = <<<'GRAPHQL'
query ConsumerProduct($sku: Sku!, $languages: [Language!], $attributeCodes: [AttributeCode!]) {
  product(sku: $sku) {
    __typename
    sku
    createdAt
    editedAt
    template { code }
    status(languages: $languages) { language value { code } }
    attributeList(first: 100, codes: $attributeCodes) {
      pageInfo { hasNextPage endCursor }
      edges { node {
        __typename
        attribute { code }
        ... on DateAttributeValue { translations(languages: $languages) { language value } }
        ... on FileAttributeValue { translations(languages: $languages) { language value { path } } }
        ... on GalleryAttributeValue { translations(languages: $languages) { language value { path } } }
        ... on ImageAttributeValue { translations(languages: $languages) { language value { path } } }
        ... on MultiSelectAttributeValue { translations(languages: $languages) { language value { code } } }
        ... on NumberAttributeValue { translations(languages: $languages) { language value } }
        ... on PriceAttributeValue { translations(languages: $languages) { language value } }
        ... on ProductRelationAttributeValue { translations(languages: $languages) { language value { sku } } }
        ... on SelectAttributeValue { translations(languages: $languages) { language value { code } } }
        ... on TextAttributeValue { translations(languages: $languages) { language value } }
        ... on TextareaAttributeValue { translations(languages: $languages) { language rawValue } }
        ... on UnitAttributeValue { translations(languages: $languages) { language value } }
      } }
    }
    ... on SimpleProduct { isVariant }
    ... on VariableProduct {
      bindings { code }
      variantList(first: 100) {
        pageInfo { hasNextPage endCursor }
        edges { node { sku } }
      }
    }
    ... on GroupingProduct {
      childrenList(first: 100) {
        pageInfo { hasNextPage endCursor }
        edges { node { quantity product {
          ... on SimpleProduct { sku }
          ... on VariableProduct { sku }
        } } }
      }
    }
  }
}
GRAPHQL;

    public const string CURRENT_PRODUCT = <<<'GRAPHQL'
query ConsumerCurrentProduct($sku: Sku!) {
  product(sku: $sku) { __typename sku createdAt editedAt }
}
GRAPHQL;

    public const string ATTRIBUTES = <<<'GRAPHQL'
query ConsumerProductAttributes(
  $sku: Sku!, $first: Int!, $after: String, $languages: [Language!], $attributeCodes: [AttributeCode!]
) {
  product(sku: $sku) { attributeList(first: $first, after: $after, codes: $attributeCodes) {
    pageInfo { hasNextPage endCursor }
    edges { node {
      __typename
      attribute { code }
      ... on DateAttributeValue { translations(languages: $languages) { language value } }
      ... on FileAttributeValue { translations(languages: $languages) { language value { path } } }
      ... on GalleryAttributeValue { translations(languages: $languages) { language value { path } } }
      ... on ImageAttributeValue { translations(languages: $languages) { language value { path } } }
      ... on MultiSelectAttributeValue { translations(languages: $languages) { language value { code } } }
      ... on NumberAttributeValue { translations(languages: $languages) { language value } }
      ... on PriceAttributeValue { translations(languages: $languages) { language value } }
      ... on ProductRelationAttributeValue { translations(languages: $languages) { language value { sku } } }
      ... on SelectAttributeValue { translations(languages: $languages) { language value { code } } }
      ... on TextAttributeValue { translations(languages: $languages) { language value } }
      ... on TextareaAttributeValue { translations(languages: $languages) { language rawValue } }
      ... on UnitAttributeValue { translations(languages: $languages) { language value } }
    } }
  } }
}
GRAPHQL;

    public const string VARIANTS = <<<'GRAPHQL'
query ConsumerProductVariants($sku: Sku!, $first: Int!, $after: String) {
  product(sku: $sku) { ... on VariableProduct { variantList(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { sku } }
  } } }
}
GRAPHQL;

    public const string CHILDREN = <<<'GRAPHQL'
query ConsumerProductChildren($sku: Sku!, $first: Int!, $after: String) {
  product(sku: $sku) { ... on GroupingProduct { childrenList(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { quantity product {
      ... on SimpleProduct { sku }
      ... on VariableProduct { sku }
    } } }
  } } }
}
GRAPHQL;
}
