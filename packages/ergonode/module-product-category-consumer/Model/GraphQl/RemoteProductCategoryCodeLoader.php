<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Model\GraphQl;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\ProductConsumer\Model\GraphQl\PaginationCursorGuard;
use Magento\Framework\Exception\LocalizedException;

class RemoteProductCategoryCodeLoader
{
    /** @var array<string, string[]> */
    private array $cache = [];

    public function __construct(private readonly GraphQlQueryClientInterface $client)
    {
    }

    /** @return string[] */
    public function load(string $sku): array
    {
        $sku = trim($sku);
        if (isset($this->cache[$sku])) {
            return $this->cache[$sku];
        }
        $codes = [];
        $after = null;
        $guard = new PaginationCursorGuard('product categories');
        do {
            $data = $this->client->query(ProductCategoryQueries::CATEGORIES, [
                'sku' => $sku,
                'first' => 100,
                'after' => $after,
            ]);
            $product = is_array($data['product'] ?? null) ? $data['product'] : null;
            if ($product === null || trim((string)($product['sku'] ?? '')) !== $sku) {
                throw new LocalizedException(__('Ergonode returned invalid product category data for "%1".', $sku));
            }
            $connection = $product['categoryList'] ?? null;
            if (!is_array($connection)
                || !is_array($connection['edges'] ?? null)
                || !is_array($connection['pageInfo'] ?? null)
                || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)
            ) {
                throw new LocalizedException(__('Ergonode returned incomplete product category data for "%1".', $sku));
            }
            foreach ($connection['edges'] as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;
                $code = is_array($node) ? ($node['code'] ?? null) : null;
                if (!is_string($code) || trim($code) === '') {
                    throw new LocalizedException(__('Ergonode returned invalid product category code for "%1".', $sku));
                }
                $codes[] = trim($code);
            }
            $after = $guard->next($connection);
        } while ($after !== null);
        $codes = array_values(array_unique($codes));
        sort($codes);

        return $this->cache[$sku] = $codes;
    }
}
