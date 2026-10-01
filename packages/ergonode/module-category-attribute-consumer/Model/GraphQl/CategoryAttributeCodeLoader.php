<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\GraphQl;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeCodeLoaderInterface;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeCodeLoader implements CategoryAttributeCodeLoaderInterface
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
        private readonly GraphQlQueryClientInterface $client,
        private readonly CursorPaginationGuardFactoryInterface $paginationGuardFactory
    ) {
    }

    public function load(): array
    {
        $codes = [];
        $after = null;
        $pagination = $this->paginationGuardFactory->create('category attributes');
        do {
            $data = $this->client->query(self::QUERY, [
                'first' => self::PAGE_SIZE,
                'after' => $after,
            ]);
            $connection = is_array($data['categoryAttributeList'] ?? null)
                ? $data['categoryAttributeList']
                : [];
            if (!is_array($connection['edges'] ?? null)
                || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)
                || !array_key_exists('endCursor', $connection['pageInfo'])
                || ($connection['edges'] !== [] && empty($connection['pageInfo']['endCursor']))
                || ($connection['pageInfo']['hasNextPage'] && $connection['edges'] === [])
            ) {
                throw new LocalizedException(__('Ergonode returned an incomplete category attribute list.'));
            }
            foreach ($connection['edges'] as $edge) {
                $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
                $code = trim((string)($node['code'] ?? ''));
                if ($code === '') {
                    throw new LocalizedException(__('Ergonode returned an invalid category attribute code.'));
                }
                $codes[$code] = $code;
            }
            $paginationInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
            $after = $pagination->next($paginationInfo);
        } while ($after !== null);

        return array_values($codes);
    }
}
