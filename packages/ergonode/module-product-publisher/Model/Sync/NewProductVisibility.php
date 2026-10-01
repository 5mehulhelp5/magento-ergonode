<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;

class NewProductVisibility
{
    private const int BATCH_SIZE = 50;

    public function __construct(private readonly GraphQlWriteScopeQueryClientInterface $client)
    {
    }

    /**
     * @param string[] $skus
     * @return string[]
     */
    public function load(array $skus, int $delayMilliseconds = 0): array
    {
        if ($skus === []) {
            return [];
        }
        if ($delayMilliseconds > 0) {
            usleep($delayMilliseconds * 1000);
        }
        $visible = [];
        foreach (array_chunk(array_values(array_unique($skus)), self::BATCH_SIZE) as $chunk) {
            $definitions = [];
            $fields = [];
            $variables = [];
            foreach ($chunk as $index => $sku) {
                $definitions[] = '$sku_' . $index . ': Sku!';
                $fields[] = 'product_' . $index . ': product(sku: $sku_' . $index . ') { sku }';
                $variables['sku_' . $index] = $sku;
            }
            $data = $this->client->queryWriteScope(
                'query NewProductVisibility(' . implode(', ', $definitions) . ') { '
                    . implode(' ', $fields) . ' }',
                $variables
            );
            foreach ($chunk as $index => $sku) {
                $alias = 'product_' . $index;
                if (!array_key_exists($alias, $data)) {
                    throw new LocalizedException(__('Ergonode omitted the visibility result for SKU "%1".', $sku));
                }
                $product = $data[$alias];
                if ($product === null) {
                    continue;
                }
                if (!is_array($product) || ($product['sku'] ?? null) !== $sku) {
                    throw new LocalizedException(__(
                        'Ergonode returned an unexpected visibility result for SKU "%1".',
                        $sku
                    ));
                }
                $visible[] = $sku;
            }
        }

        return $visible;
    }
}
