<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\GraphQl;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;

class RemoteProductCategoryStateLoader
{
    private const int BATCH_SIZE = 50;
    private const int PAGE_SIZE = 100;

    public function __construct(private readonly GraphQlWriteScopeQueryClientInterface $client)
    {
    }

    /**
     * @param string[] $skus
     * @return array<string, string[]|null> Values are keyed by "sku:<native Ergonode SKU>".
     */
    public function load(array $skus): array
    {
        $states = [];
        foreach ($skus as $sku) {
            $sku = trim($sku);
            if ($sku === '') {
                continue;
            }
            $states[$this->stateKey($sku)] = [
                'sku' => $sku,
                'category_codes' => [],
                'after' => null,
                'seen_cursors' => [],
            ];
        }
        $results = [];
        $pending = array_keys($states);

        while ($pending !== []) {
            $nextPending = [];
            foreach (array_chunk($pending, self::BATCH_SIZE) as $stateKeys) {
                [$query, $variables] = $this->query($stateKeys, $states);
                $data = $this->client->queryWriteScope($query, $variables);
                foreach ($stateKeys as $index => $stateKey) {
                    $alias = 'product_' . $index;
                    if (!array_key_exists($alias, $data)) {
                        throw new LocalizedException(__(
                            'Ergonode returned no product category result for SKU "%1".',
                            $states[$stateKey]['sku']
                        ));
                    }
                    $product = $data[$alias];
                    if ($product === null) {
                        $results[$stateKey] = null;
                        continue;
                    }
                    if (!is_array($product)) {
                        throw new LocalizedException(__('Ergonode returned an invalid product category result.'));
                    }
                    $remoteSku = trim((string)($product['sku'] ?? ''));
                    if ($remoteSku !== $states[$stateKey]['sku']) {
                        throw new LocalizedException(__(
                            'Ergonode returned product SKU "%1" while loading "%2".',
                            $remoteSku,
                            $states[$stateKey]['sku']
                        ));
                    }
                    $connection = $product['categoryList'] ?? null;
                    if (!is_array($connection)
                        || !is_array($connection['edges'] ?? null)
                        || !is_array($connection['pageInfo'] ?? null)
                        || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)
                    ) {
                        throw new LocalizedException(__(
                            'Ergonode returned incomplete category pagination for product "%1".',
                            $remoteSku
                        ));
                    }
                    foreach ($connection['edges'] as $edge) {
                        $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
                        $code = trim((string)($node['code'] ?? ''));
                        if ($code === '') {
                            throw new LocalizedException(__(
                                'Ergonode returned an empty category code for product "%1".',
                                $remoteSku
                            ));
                        }
                        $states[$stateKey]['category_codes'][] = $code;
                    }
                    $pageInfo = $connection['pageInfo'];
                    if (!$pageInfo['hasNextPage']) {
                        $categoryCodes = array_values(array_unique($states[$stateKey]['category_codes']));
                        sort($categoryCodes);
                        $results[$stateKey] = $categoryCodes;
                        continue;
                    }
                    $cursor = trim((string)($pageInfo['endCursor'] ?? ''));
                    if ($cursor === '' || isset($states[$stateKey]['seen_cursors'][$cursor])) {
                        throw new LocalizedException(__(
                            'Ergonode returned invalid category pagination for product "%1".',
                            $remoteSku
                        ));
                    }
                    $states[$stateKey]['seen_cursors'][$cursor] = true;
                    $states[$stateKey]['after'] = $cursor;
                    $nextPending[] = $stateKey;
                }
            }
            $pending = $nextPending;
        }

        return $results;
    }

    /**
     * @param string[] $stateKeys
     * @param array<string, array{
     *     sku: string,
     *     category_codes: string[],
     *     after: string|null,
     *     seen_cursors: array<string, true>
     * }> $states
     * @return array{string, array<string, string|null>}
     */
    private function query(array $stateKeys, array $states): array
    {
        $definitions = [];
        $fields = [];
        $variables = [];
        foreach ($stateKeys as $index => $stateKey) {
            $skuVariable = 'sku_' . $index;
            $afterVariable = 'after_' . $index;
            $definitions[] = '  $' . $skuVariable . ': Sku!';
            $definitions[] = '  $' . $afterVariable . ': String';
            $fields[] = '  product_' . $index . ': product(sku: $' . $skuVariable . ') {';
            $fields[] = '    sku';
            $fields[] = '    categoryList(first: ' . self::PAGE_SIZE . ', after: $' . $afterVariable . ') {';
            $fields[] = '      pageInfo { hasNextPage endCursor }';
            $fields[] = '      edges { node { code } }';
            $fields[] = '    }';
            $fields[] = '  }';
            $variables[$skuVariable] = $states[$stateKey]['sku'];
            $variables[$afterVariable] = $states[$stateKey]['after'];
        }

        return [
            "query PublisherProductCategories(\n"
                . implode("\n", $definitions)
                . "\n) {\n"
                . implode("\n", $fields)
                . "\n}",
            $variables,
        ];
    }

    private function stateKey(string $sku): string
    {
        return 'sku:' . $sku;
    }
}
