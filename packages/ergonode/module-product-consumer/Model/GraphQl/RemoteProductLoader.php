<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use Magento\Framework\Exception\LocalizedException;

class RemoteProductLoader
{
    private const array TYPE_MAP = [
        'SimpleProduct' => 'simple',
        'VariableProduct' => 'variable',
        'GroupingProduct' => 'grouping',
    ];

    public function __construct(
        private readonly ProductAttributeSourcePreparationInterface $attributePreparation,
        private readonly GraphQlQueryClientInterface $client,
        private readonly ProductAttributeMappingProviderInterface $mappingProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly RemoteProductAttributeFactory $attributeFactory,
        private readonly ProductImportConfig $config,
        private readonly ProductAttributeCodeProviderPool $attributeCodeProviders
    ) {
    }

    /** @param array<string, mixed>|null $snapshot */
    public function loadCurrent(string $sku, ?array $snapshot = null): ?RemoteProduct
    {
        $data = $this->client->query(ProductQueries::CURRENT_PRODUCT, ['sku' => $sku]);
        $current = is_array($data['product'] ?? null) ? $data['product'] : null;
        if ($current === null) {
            return null;
        }
        if ($snapshot !== null && $this->sameVersion($current, $snapshot)) {
            return $this->load($sku, $snapshot);
        }

        return $this->load($sku);
    }

    /** @param array<string, mixed>|null $snapshot */
    public function load(string $sku, ?array $snapshot = null): ?RemoteProduct
    {
        $this->attributePreparation->prepare();
        $sku = trim($sku);
        $languages = $this->languageMappingProvider->getLanguageCodes();
        $attributeCodes = $this->attributeCodes();
        $product = $snapshot;
        if ($product === null) {
            $data = $this->client->query(ProductQueries::PRODUCT, [
                'sku' => $sku,
                'languages' => $languages,
                'attributeCodes' => $attributeCodes,
            ]);
            $product = is_array($data['product'] ?? null) ? $data['product'] : null;
        }
        if ($product === null) {
            return null;
        }
        if (trim((string)($product['sku'] ?? '')) !== $sku) {
            throw new LocalizedException(__('Ergonode returned a product snapshot for an unexpected SKU.'));
        }
        $typeName = (string)($product['__typename'] ?? '');
        $type = self::TYPE_MAP[$typeName] ?? null;
        if ($type === null) {
            throw new LocalizedException(__('Unsupported Ergonode product type "%1".', $typeName));
        }

        $attributes = $this->allAttributes($sku, $product, $languages, $attributeCodes);
        [$bindings, $variants] = $type === 'variable'
            ? $this->allVariableRelations($sku, $product)
            : [[], []];
        $children = $type === 'grouping' ? $this->allGroupingRelations($sku, $product) : [];
        $template = is_array($product['template'] ?? null) ? $product['template'] : [];

        return new RemoteProduct(
            $sku,
            $type,
            trim((string)($template['code'] ?? '')),
            !empty($product['isVariant']),
            $this->statuses($product['status'] ?? []),
            array_values($attributes),
            $bindings,
            $variants,
            $children
        );
    }

    /**
     * @param array<string, mixed> $product
     * @param string[] $languages
     * @param string[] $attributeCodes
     * @return array<string, RemoteProductAttribute>
     */
    private function allAttributes(string $sku, array $product, array $languages, array $attributeCodes): array
    {
        $connection = is_array($product['attributeList'] ?? null) ? $product['attributeList'] : [];
        $attributes = $this->attributes($connection);
        $guard = new PaginationCursorGuard('product attributes');
        $after = $guard->next($connection);
        while ($after !== null) {
            $data = $this->client->query(ProductQueries::ATTRIBUTES, [
                'sku' => $sku,
                'first' => 100,
                'after' => $after,
                'languages' => $languages,
                'attributeCodes' => $attributeCodes,
            ]);
            $remote = $this->requiredProduct($data, $sku, 'attributes');
            $connection = is_array($remote['attributeList'] ?? null) ? $remote['attributeList'] : [];
            $attributes = array_replace($attributes, $this->attributes($connection));
            $after = $guard->next($connection);
        }

        return $attributes;
    }

    /** @param array<string, mixed> $product @return array{0: string[], 1: string[]} */
    private function allVariableRelations(string $sku, array $product): array
    {
        $bindings = [];
        foreach ((array)($product['bindings'] ?? []) as $binding) {
            $code = is_array($binding) ? trim((string)($binding['code'] ?? '')) : '';
            if ($code !== '') {
                $bindings[] = $code;
            }
        }
        $connection = is_array($product['variantList'] ?? null) ? $product['variantList'] : [];
        $variants = $this->productSkus($connection);
        $guard = new PaginationCursorGuard('product variants');
        $after = $guard->next($connection);
        while ($after !== null) {
            $data = $this->client->query(ProductQueries::VARIANTS, [
                'sku' => $sku,
                'first' => 100,
                'after' => $after,
            ]);
            $remote = $this->requiredProduct($data, $sku, 'variants');
            $connection = is_array($remote['variantList'] ?? null) ? $remote['variantList'] : [];
            $variants = [...$variants, ...$this->productSkus($connection)];
            $after = $guard->next($connection);
        }
        $bindings = array_values(array_unique($bindings));
        $variants = array_values(array_unique($variants));
        sort($bindings);
        sort($variants);

        return [$bindings, $variants];
    }

    /** @param array<string, mixed> $product @return array<string, int> */
    private function allGroupingRelations(string $sku, array $product): array
    {
        $connection = is_array($product['childrenList'] ?? null) ? $product['childrenList'] : [];
        $children = $this->children($connection);
        $guard = new PaginationCursorGuard('grouped product children');
        $after = $guard->next($connection);
        while ($after !== null) {
            $data = $this->client->query(ProductQueries::CHILDREN, [
                'sku' => $sku,
                'first' => 100,
                'after' => $after,
            ]);
            $remote = $this->requiredProduct($data, $sku, 'children');
            $connection = is_array($remote['childrenList'] ?? null) ? $remote['childrenList'] : [];
            $children = array_replace($children, $this->children($connection));
            $after = $guard->next($connection);
        }
        ksort($children);

        return $children;
    }

    /** @param array<string, mixed> $connection @return array<string, RemoteProductAttribute> */
    private function attributes(array $connection): array
    {
        $result = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $attribute = is_array($node['attribute'] ?? null) ? $node['attribute'] : [];
            $code = trim((string)($attribute['code'] ?? ''));
            $typeName = (string)($node['__typename'] ?? '');
            $type = $this->typeResolver->fromValueTypeName($typeName);
            if ($code === '' || $type === null) {
                throw new LocalizedException(__('Ergonode returned an unsupported product attribute value.'));
            }
            $result[$code] = $this->attributeFactory->fromPayload(
                $code,
                $type,
                $node['translations'] ?? []
            );
        }

        return $result;
    }

    /** @param array<string, mixed> $connection @return string[] */
    private function productSkus(array $connection): array
    {
        $skus = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $sku = trim((string)($node['sku'] ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    /** @param array<string, mixed> $connection @return array<string, int> */
    private function children(array $connection): array
    {
        $children = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $child = is_array($node['product'] ?? null) ? $node['product'] : [];
            $childSku = trim((string)($child['sku'] ?? ''));
            $quantity = $node['quantity'] ?? null;
            if ($childSku === '' || !is_int($quantity) || $quantity < 1) {
                throw new LocalizedException(__('Ergonode returned an invalid grouped product child.'));
            }
            if (isset($children[$childSku]) && $children[$childSku] !== $quantity) {
                throw new LocalizedException(__('Ergonode returned conflicting quantities for child "%1".', $childSku));
            }
            $children[$childSku] = $quantity;
        }

        return $children;
    }

    /** @return array<string, string> */
    private function statuses(mixed $items): array
    {
        $statuses = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $value = is_array($item) && is_array($item['value'] ?? null) ? $item['value'] : [];
            $language = is_array($item) ? trim((string)($item['language'] ?? '')) : '';
            $code = trim((string)($value['code'] ?? ''));
            if ($language !== '' && $code !== '') {
                $statuses[$language] = $code;
            }
        }
        ksort($statuses);

        return $statuses;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function requiredProduct(array $data, string $sku, string $context): array
    {
        $product = is_array($data['product'] ?? null) ? $data['product'] : null;
        if ($product === null) {
            throw new LocalizedException(__('Product "%1" disappeared while loading %2.', $sku, $context));
        }

        return $product;
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

    /** @param array<string, mixed> $current @param array<string, mixed> $snapshot */
    private function sameVersion(array $current, array $snapshot): bool
    {
        return trim((string)($current['sku'] ?? '')) === trim((string)($snapshot['sku'] ?? ''))
            && (string)($current['__typename'] ?? '') === (string)($snapshot['__typename'] ?? '')
            && (string)($current['createdAt'] ?? '') === (string)($snapshot['createdAt'] ?? '')
            && (string)($current['editedAt'] ?? '') === (string)($snapshot['editedAt'] ?? '');
    }
}
