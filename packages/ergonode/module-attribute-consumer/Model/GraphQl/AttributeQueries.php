<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\GraphQl;

class AttributeQueries
{
    public const string ATTRIBUTE_STREAM = <<<'GRAPHQL'
query ErgonodeAttributeStream($first: Int!, $after: String, $languages: [Language!]) {
  attributeStream(first: $first, after: $after) {
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node {
        __typename
        code
        name(languages: $languages) {
          language
          value
        }
        scope
        ... on DateAttribute {
          format
        }
        ... on NumericAttribute {
          unique
        }
        ... on PriceAttribute {
          currency
        }
        ... on TextAttribute {
          unique
        }
        ... on TextareaAttribute {
          richEdit
        }
        ... on UnitAttribute {
          unit {
            name
            symbol
          }
        }
      }
    }
  }
}
GRAPHQL;

    public const string ATTRIBUTE_OPTION_LIST = <<<'GRAPHQL'
query ErgonodeAttributeOptionList($code: AttributeCode!, $first: Int!, $after: String, $languages: [Language!]) {
  attributeOptionList(code: $code, first: $first, after: $after) {
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node {
        code
        name(languages: $languages) {
          language
          value
        }
      }
    }
  }
}
GRAPHQL;
}
