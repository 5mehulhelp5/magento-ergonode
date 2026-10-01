<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\GraphQl;

class CategoryAttributeQueries
{
    public const string CATEGORY_FIELDS = <<<'GRAPHQL'
    code
    name(languages: $languages) { language value }
    attributeList(first: $first, after: $after) {
      pageInfo { hasNextPage endCursor }
      edges {
        node {
          __typename
          attribute { __typename code }
          ... on DateAttributeValue {
            dateAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on FileAttributeValue {
            fileAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on GalleryAttributeValue {
            galleryAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on ImageAttributeValue {
            imageAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on MultiSelectAttributeValue {
            multiSelectAttributeValueTranslations: translations(languages: $languages) { language value { code } }
          }
          ... on NumberAttributeValue {
            numericAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on PriceAttributeValue {
            priceAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on ProductRelationAttributeValue {
            productRelationAttributeValueTranslations: translations(languages: $languages) { language value { sku } }
          }
          ... on SelectAttributeValue {
            selectAttributeValueTranslations: translations(languages: $languages) { language value { code } }
          }
          ... on TextAttributeValue {
            textAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on TextareaAttributeValue {
            textareaAttributeValueTranslations: translations(languages: $languages) { language rawValue }
          }
          ... on UnitAttributeValue {
            unitAttributeValueTranslations: translations(languages: $languages) { language value }
          }
        }
      }
    }
GRAPHQL;
}
