<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\GraphQl;

class LanguageQuery
{
    public const string LANGUAGE_LIST = <<<'GRAPHQL'
query ErgonodeLanguageList($first: Int!, $after: String) {
  languageList(first: $first, after: $after) {
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node
    }
  }
}
GRAPHQL;
}
