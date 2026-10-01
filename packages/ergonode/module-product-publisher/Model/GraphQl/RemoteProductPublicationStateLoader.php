<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\GraphQl;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * @phpstan-type RemoteState array{template: string, translations: array<string, array<string, true>>}
 */
class RemoteProductPublicationStateLoader
{
    private const int BATCH_SIZE = 50;
    private const int PAGE_SIZE = 100;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $client,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Only complete products are returned, keyed by "sku:<native SKU>".
     * An unavailable optimization read preserves the original publication plan.
     *
     * @param string[] $skus
     * @return array<string, RemoteState>
     */
    public function load(array $skus): array
    {
        $results = [];
        foreach (array_chunk(array_values(array_unique($skus)), self::BATCH_SIZE) as $chunk) {
            try {
                $results += $this->loadChunk($chunk);
            } catch (Exception $exception) {
                $this->logger->warning('Ergonode publication comparison unavailable; preserving planned writes.', [
                    'exception_class' => $exception::class,
                    'product_count' => count($chunk),
                ]);
            }
        }
        return $results;
    }

    /** @param string[] $skus @return array<string, RemoteState> */
    private function loadChunk(array $skus): array
    {
        $pending = [];
        $states = [];
        $seen = [];
        foreach ($skus as $index => $sku) {
            $pending[$index] = null;
        }
        while ($pending !== []) {
            [$query, $variables] = $this->query($skus, $pending);
            $data = $this->client->queryWriteScope($query, $variables);
            foreach (array_keys($pending) as $index) {
                $alias = 'product_' . $index;
                if (!array_key_exists($alias, $data)) {
                    throw new LocalizedException(__('Ergonode omitted a publication comparison result.'));
                }
                $page = $this->page($data[$alias], $skus[$index]);
                $key = 'sku:' . $skus[$index];
                if ($page === null) {
                    unset($pending[$index], $states[$key]);
                    continue;
                }
                $previous = $states[$key] ?? $page['state'];
                if ($previous['template'] !== $page['state']['template']) {
                    throw new LocalizedException(__('Ergonode product template changed during pagination.'));
                }
                $states[$key] = [
                    'template' => $previous['template'],
                    'translations' => array_replace_recursive(
                        $previous['translations'],
                        $page['state']['translations']
                    ),
                ];
                unset($pending[$index]);
                $cursor = $page['after'];
                if ($cursor !== null) {
                    if (isset($seen[$index][$cursor])) {
                        throw new LocalizedException(__('Ergonode repeated a publication comparison cursor.'));
                    }
                    $seen[$index][$cursor] = true;
                    $pending[$index] = $cursor;
                }
            }
        }
        return $states;
    }

    /** @return array{state: RemoteState, after: string|null}|null */
    private function page(mixed $product, string $sku): ?array
    {
        if ($product === null) {
            return null;
        }
        if (!is_array($product) || ($product['sku'] ?? null) !== $sku
            || !is_array($product['template'] ?? null)
            || !is_string($product['template']['code'] ?? null) || $product['template']['code'] === ''
        ) {
            throw new LocalizedException(__('Ergonode returned an invalid publication comparison product.'));
        }
        $connection = $product['attributeList'] ?? null;
        if (!is_array($connection) || !is_array($connection['edges'] ?? null)
            || !array_is_list($connection['edges'])
            || !is_array($connection['pageInfo'] ?? null)
            || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)
        ) {
            throw new LocalizedException(__('Ergonode returned incomplete publication comparison pagination.'));
        }
        $cursor = null;
        if ($connection['pageInfo']['hasNextPage']) {
            $cursor = $connection['pageInfo']['endCursor'] ?? null;
            if (!is_string($cursor) || trim($cursor) === '') {
                throw new LocalizedException(__('Ergonode returned an invalid publication comparison cursor.'));
            }
        }
        return [
            'state' => [
                'template' => $product['template']['code'],
                'translations' => $this->translations($connection['edges']),
            ],
            'after' => $cursor,
        ];
    }

    /** @param array<int, mixed> $edges @return array<string, array<string, true>> */
    private function translations(array $edges): array
    {
        $translations = [];
        foreach ($edges as $edge) {
            $node = is_array($edge) ? ($edge['node'] ?? null) : null;
            if (!is_array($node) || !is_array($node['attribute'] ?? null)
                || !is_string($node['attribute']['code'] ?? null) || $node['attribute']['code'] === ''
                || !is_array($node['translations'] ?? null)
            ) {
                throw new LocalizedException(__('Ergonode returned incomplete attribute translation presence.'));
            }
            foreach ($node['translations'] as $translation) {
                if (!is_array($translation) || !is_string($translation['language'] ?? null)
                    || $translation['language'] === ''
                ) {
                    throw new LocalizedException(__('Ergonode returned an invalid attribute translation language.'));
                }
                $translations[$node['attribute']['code']][$translation['language']] = true;
            }
        }
        return $translations;
    }

    /**
     * @param string[] $skus
     * @param array<int, string|null> $pending
     * @return array{string, array<string, string|null>}
     */
    private function query(array $skus, array $pending): array
    {
        $definitions = [];
        $fields = [];
        $variables = [];
        foreach ($pending as $index => $after) {
            $definitions[] = '$sku_' . $index . ': Sku!, $after_' . $index . ': String';
            $fields[] = 'product_' . $index . ': product(sku: $sku_' . $index . ') {'
                . ' sku template { code } attributeList(first: ' . self::PAGE_SIZE . ', after: $after_' . $index . ') {'
                . ' pageInfo { hasNextPage endCursor } edges { node { attribute { code } translations { language } } }'
                . ' } }';
            $variables['sku_' . $index] = $skus[$index];
            $variables['after_' . $index] = $after;
        }
        return [
            'query ProductPublicationComparison(' . implode(', ', $definitions) . ') { '
                . implode(' ', $fields) . ' }',
            $variables,
        ];
    }
}
