<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\GraphQl;

use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;

class WriteScopeCategoryAttributeCodeLoader implements WriteScopeCategoryAttributeCodeLoaderInterface
{
    private const int PAGE_SIZE = 100;

    private const string QUERY = <<<'GRAPHQL'
query ErgonodeCategoryAttributeCodes($first: Int!, $after: String) {
  categoryAttributeList(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { code } }
  }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $client,
        private readonly CursorPaginationGuardFactoryInterface $paginationGuardFactory
    ) {
    }

    public function loadWriteScope(): array
    {
        $codes = [];
        $after = null;
        $pagination = $this->paginationGuardFactory->create('category attributes');
        do {
            $data = $this->client->queryWriteScope(self::QUERY, [
                'first' => self::PAGE_SIZE,
                'after' => $after,
            ]);
            $connection = is_array($data['categoryAttributeList'] ?? null)
                ? $data['categoryAttributeList']
                : [];
            foreach ((array)($connection['edges'] ?? []) as $edge) {
                $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
                $code = trim((string)($node['code'] ?? ''));
                if ($code !== '') {
                    $codes[$code] = $code;
                }
            }
            $paginationInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
            $after = $pagination->next($paginationInfo);
        } while ($after !== null);

        return array_values($codes);
    }
}
