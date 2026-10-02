<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\GraphQl;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Magento\Framework\Exception\LocalizedException;

class MultimediaClient
{
    public const string ITEM_QUERY = 'query ErgonodeMultimedia($path: MultimediaPath!) {'
        . ' multimedia(path: $path) { path name extension mime size url } }';
    public const string STREAM_QUERY = 'query ErgonodeMultimediaStream($first: Int!, $after: String) {'
        . ' multimediaStream(first: $first, after: $after) {'
        . ' pageInfo { hasNextPage endCursor }'
        . ' edges { cursor node { path name extension mime size url } } } }';

    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly PageQueryRetrierInterface $retrier
    ) {
    }

    /**
     * Load one multimedia object.
     *
     * @return array{path:string,name:string,extension:string,mime:string,size:int,url:string}
     */
    public function get(string $path): array
    {
        $data = $this->client->query(self::ITEM_QUERY, ['path' => $path]);
        $item = $data['multimedia'] ?? null;
        if (!is_array($item)
            || (string)($item['path'] ?? '') !== $path
            || trim((string)($item['url'] ?? '')) === ''
        ) {
            throw new LocalizedException(__('Ergonode multimedia "%1" is unavailable.', $path));
        }
        return [
            'path' => $path,
            'name' => (string)($item['name'] ?? ''),
            'extension' => (string)($item['extension'] ?? ''),
            'mime' => (string)($item['mime'] ?? ''),
            'size' => (int)($item['size'] ?? 0),
            'url' => (string)$item['url'],
        ];
    }

    /**
     * Load a page of multimedia changes.
     *
     * @return array{items:list<array<string,mixed>>,cursor:?string,has_more:bool,page_size:int}
     */
    public function stream(?string $cursor, int $size): array
    {
        $data = $this->retrier->query(
            [200, 100, 50, 25, 10, 5, 1],
            $size,
            fn (int $page): array => $this->client->query(
                self::STREAM_QUERY,
                ['first' => $page, 'after' => $cursor]
            )
        );
        $connection = is_array($data['multimediaStream'] ?? null) ? $data['multimediaStream'] : [];
        $items = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $path = trim((string)($node['path'] ?? ''));
            $edgeCursor = trim((string)($edge['cursor'] ?? ''));
            if ($path === '' || $edgeCursor === '') {
                throw new LocalizedException(__('Ergonode multimedia stream returned an invalid edge.'));
            }
            $items[] = [
                'cursor' => $edgeCursor,
                'path' => $path,
                'name' => (string)($node['name'] ?? ''),
                'extension' => (string)($node['extension'] ?? ''),
                'mime' => (string)($node['mime'] ?? ''),
                'size' => (int)($node['size'] ?? 0),
                'url' => (string)($node['url'] ?? ''),
            ];
        }
        $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
        $next = trim((string)($pageInfo['endCursor'] ?? '')) ?: null;
        $more = !empty($pageInfo['hasNextPage']);
        if ($more && ($next === null || $next === $cursor)) {
            throw new LocalizedException(__('Ergonode returned an invalid multimedia stream cursor.'));
        }
        return [
            'items' => $items,
            'cursor' => $next,
            'has_more' => $more,
            'page_size' => (int)$data['_page_size'],
        ];
    }
}
