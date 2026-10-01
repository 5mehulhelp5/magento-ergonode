<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\GraphQl;

class CategoryQueries
{
    public const string CATEGORY_TREE_STREAM = <<<'GRAPHQL'
query ErgonodeCategoryTreeStream($first: Int!, $after: String) {
  categoryTreeStream(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      cursor
      node {
        code
      }
    }
  }
}
GRAPHQL;

    public const string CATEGORY_TREE = <<<'GRAPHQL'
query ErgonodeCategoryTree(
  $code: CategoryTreeCode!,
  $first: Int!,
  $after: String,
  $languages: [Language!]
) {
  categoryTree(code: $code) {
    code
    name(languages: $languages) {
      language
      value
    }
    categoryTreeLeafList(first: $first, after: $after) {
      pageInfo {
        hasNextPage
        endCursor
      }
      totalCount
      edges {
        node {
          category {
            code
            name(languages: $languages) {
              language
              value
            }
          }
          parentCategory {
            code
          }
        }
      }
    }
  }
}
GRAPHQL;
    public const int ENTITY_BATCH_SIZE = 50;
    public const string NAME_FIELDS = 'code name(languages: $languages) { language value }';

    /**
     * Build a bounded query; callers retain ownership of read/write credentials and normalization.
     * @param list<string> $codes
     * @param string[] $languages
     * @param array<string, string|null> $cursors
     * @return array{document: string, variables: array<string, mixed>}
     */
    public function entityBatch(
        array $codes,
        array $languages,
        string $selection = self::NAME_FIELDS,
        array $cursors = [],
        ?int $pageSize = null
    ): array {
        $definitions = ['$languages: [Language!]'];
        $variables = ['languages' => $languages];
        if ($pageSize !== null) {
            $definitions[] = '$first: Int!';
            $variables['first'] = $pageSize;
        }
        $fields = [];
        foreach (array_values($codes) as $index => $code) {
            $definitions[] = '$code_' . $index . ': CategoryCode!';
            $variables['code_' . $index] = $code;
            if ($pageSize !== null) {
                $definitions[] = '$after_' . $index . ': String';
                $variables['after_' . $index] = $cursors[$code] ?? null;
            }
            $fields[] = 'category_' . $index . ': category(code: $code_' . $index . ') {'
                . str_replace('$after', '$after_' . $index, $selection) . '}';
        }

        return [
            'document' => 'query ErgonodeCategoryBatch(' . implode(', ', $definitions) . ') {'
                . implode("\n", $fields) . '}',
            'variables' => $variables,
        ];
    }
}
