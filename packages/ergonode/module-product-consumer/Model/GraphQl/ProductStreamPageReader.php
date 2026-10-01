<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Magento\Framework\Exception\LocalizedException;

class ProductStreamPageReader
{
    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly PageQueryRetrierInterface $pageQueryRetrier,
        private readonly ProductAttributeMappingProviderInterface $mappingProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ProductImportConfig $config,
        private readonly ProductAttributeCodeProviderPool $attributeCodeProviders
    ) {
    }

    /**
     * @return array{
     *     items: array<int, array{sku: string, payload: array<string, mixed>}>,
     *     cursor: string|null,
     *     has_more: bool,
     *     page_size: int
     * }
     */
    public function readChanged(?string $cursor, int $requestedPageSize): array
    {
        $data = $this->pageQueryRetrier->query(
            [50, 25, 10, 5, 1],
            $requestedPageSize,
            fn (int $pageSize): array => $this->client->query(ProductQueries::PRODUCT_STREAM, [
                'first' => $pageSize,
                'after' => $cursor,
                'languages' => $this->languageMappingProvider->getLanguageCodes(),
                'attributeCodes' => $this->attributeCodes(),
            ])
        );
        $connection = is_array($data['productStream'] ?? null) ? $data['productStream'] : [];
        $items = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $sku = trim((string)($node['sku'] ?? ''));
            if ($sku !== '') {
                $items[$sku] = ['sku' => $sku, 'payload' => $node];
            }
        }

        return $this->result($connection, $cursor, array_values($items), (int)$data['_page_size']);
    }

    /**
     * @return array{
     *     items: array<int, array{sku: string, payload: null}>,
     *     cursor: string|null,
     *     has_more: bool,
     *     page_size: int
     * }
     */
    public function readDeleted(?string $cursor, int $requestedPageSize): array
    {
        $data = $this->pageQueryRetrier->query(
            [50, 25, 10, 5, 1],
            $requestedPageSize,
            fn (int $pageSize): array => $this->client->query(ProductQueries::PRODUCT_DELETED_STREAM, [
                'first' => $pageSize,
                'after' => $cursor,
            ])
        );
        $connection = is_array($data['productDeletedStream'] ?? null) ? $data['productDeletedStream'] : [];
        $items = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $sku = is_array($edge) ? trim((string)($edge['node'] ?? '')) : '';
            if ($sku !== '') {
                $items[$sku] = ['sku' => $sku, 'payload' => null];
            }
        }

        return $this->result($connection, $cursor, array_values($items), (int)$data['_page_size']);
    }

    /**
     * @param array<string, mixed> $connection
     * @param array<int, array{sku: string, payload: array<string, mixed>|null}> $items
     * @return array{
     *     items: array<int, array{sku: string, payload: array<string, mixed>|null}>,
     *     cursor: string|null,
     *     has_more: bool,
     *     page_size: int
     * }
     */
    private function result(array $connection, ?string $cursor, array $items, int $pageSize): array
    {
        $pageInfo = is_array($connection['pageInfo'] ?? null) ? $connection['pageInfo'] : [];
        $hasMore = !empty($pageInfo['hasNextPage']);
        $next = trim((string)($pageInfo['endCursor'] ?? '')) ?: null;
        if ($hasMore && ($next === null || $next === $cursor)) {
            throw new LocalizedException(__('Ergonode returned an invalid product stream cursor.'));
        }

        return ['items' => $items, 'cursor' => $next, 'has_more' => $hasMore, 'page_size' => $pageSize];
    }

    /** @return string[] */
    private function attributeCodes(): array
    {
        $codes = [$this->config->getTypeAttributeCode(), ...$this->attributeCodeProviders->getAttributeCodes()];
        foreach ($this->mappingProvider->getMappings() as $mapping) {
            $codes[] = (string)$mapping['ergonode_attribute_code'];
        }

        return array_values(array_unique(array_filter(array_map('trim', $codes))));
    }
}
