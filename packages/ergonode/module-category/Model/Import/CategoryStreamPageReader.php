<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Core\Model\GraphQl\Client;
use Magento\Framework\Exception\LocalizedException;

class CategoryStreamPageReader
{
    public const int PAGE_SIZE = 100;

    public function __construct(
        private readonly Client $client,
        private readonly PaginationStateResolver $paginationStateResolver
    ) {
    }

    /**
     * @return array{codes: string[], cursor: string|null}
     * @throws LocalizedException
     */
    public function readAll(string $query, string $field, ?string $cursor): array
    {
        $codes = [];
        do {
            $page = $this->read($query, $field, $cursor);
            foreach ($page['codes'] as $code) {
                $codes[$code] = $code;
            }
            $cursor = $page['cursor'] ?? $cursor;
        } while ($page['has_more']);

        return [
            'codes' => array_values($codes),
            'cursor' => $cursor,
        ];
    }

    /**
     * @return array{codes: string[], cursor: string|null, has_more: bool}
     * @throws LocalizedException
     */
    private function read(string $query, string $field, ?string $cursor): array
    {
        $data = $this->client->query($query, ['first' => self::PAGE_SIZE, 'after' => $cursor]);
        $connection = is_array($data[$field] ?? null) ? $data[$field] : [];
        $codes = [];
        $lastEdgeCursor = null;
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            if (!is_array($edge)) {
                continue;
            }
            $node = $edge['node'] ?? null;
            $code = trim((string)(is_array($node) ? ($node['code'] ?? '') : $node));
            if ($code !== '') {
                $codes[] = $code;
            }
            $edgeCursor = trim((string)($edge['cursor'] ?? ''));
            if ($edgeCursor !== '') {
                $lastEdgeCursor = $edgeCursor;
            }
        }
        $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
        $pagination = $this->paginationStateResolver->resolve(
            $pageInfo,
            $cursor,
            __('Ergonode returned invalid stream pagination for %1.', $field),
            $lastEdgeCursor
        );

        return ['codes' => array_values(array_unique($codes))] + $pagination;
    }
}
