<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\GraphQl;

class TemplateStructureQueries
{
    public const string TEMPLATE_DETAILS = <<<'GRAPHQL'
query ErgonodeTemplateStructure(
  $code: TemplateCode!
  $attributeFirst: Int!
  $attributeAfter: String
  $sectionFirst: Int!
  $sectionAfter: String
) {
  template(code: $code) {
    code
    name {
      language
      value
    }
    attributeList(first: $attributeFirst, after: $attributeAfter) {
      pageInfo {
        hasNextPage
        endCursor
      }
      edges {
        node {
          code
        }
      }
    }
    sectionList(first: $sectionFirst, after: $sectionAfter) {
      pageInfo {
        hasNextPage
        endCursor
      }
      edges {
        node {
          code
          name {
            language
            value
          }
        }
      }
    }
  }
}
GRAPHQL;

    public const string SECTION_ATTRIBUTES = <<<'GRAPHQL'
query ErgonodeSectionAttributes($code: SectionCode!, $first: Int!, $after: String) {
  section(code: $code) {
    code
    attributeList(first: $first, after: $after) {
      pageInfo {
        hasNextPage
        endCursor
      }
      edges {
        node {
          code
        }
      }
    }
  }
}
GRAPHQL;
}
