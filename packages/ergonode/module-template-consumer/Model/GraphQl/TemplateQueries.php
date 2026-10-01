<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\GraphQl;

class TemplateQueries
{
    public const string TEMPLATE_LIST = <<<'GRAPHQL'
query ErgonodeTemplateList($first: Int!, $after: String) {
  templateList(first: $first, after: $after) {
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
GRAPHQL;

    public const string TEMPLATE_DETAILS = <<<'GRAPHQL'
query ErgonodeTemplateDetails($code: TemplateCode!) {
  template(code: $code) {
    code
    name {
      language
      value
    }
  }
}
GRAPHQL;
}
