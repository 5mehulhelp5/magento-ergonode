<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Mapping;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataSourceInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class RemoteAttributeMetadataSource implements ErgonodeMetadataSourceInterface
{
    private const int PAGE_SIZE = 200;
    private const int CACHE_LIFETIME = 3600;
    private const string CACHE_TAG = 'ERGONODE_PRODUCT_ATTRIBUTE_UI';
    private const string ATTRIBUTE_QUERY = <<<'GRAPHQL'
query ProductAttributeMappingDefinitions($first: Int!, $after: String) {
  attributeStream(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        __typename
        code
        scope
        name { language value }
      }
    }
  }
}
GRAPHQL;
    private const string OPTION_QUERY = <<<'GRAPHQL'
query ProductAttributeMappingOptions($code: AttributeCode!, $first: Int!, $after: String) {
  attributeOptionList(code: $code, first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { code name { language value } } }
  }
}
GRAPHQL;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $attributes = null;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $options = [];

    public function __construct(
        private readonly GraphQlQueryClientInterface $client,
        private readonly ErgonodeAttributeTypeResolverInterface $types,
        private readonly CursorPaginationGuardFactoryInterface $guards,
        private readonly ConfigProvider $config,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider
    ) {
    }

    public function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        if (!$this->config->isEnabled()) {
            return [];
        }

        $cached = $this->cache->load($this->cacheKey());
        if ($cached === false) {
            return $this->attributes = [];
        }

        $attributes = $this->json->unserialize($cached);

        return $this->attributes = is_array($attributes) ? array_values($attributes) : [];
    }

    public function getOptions(string $attributeCode): array
    {
        if (isset($this->options[$attributeCode])) {
            return $this->options[$attributeCode];
        }
        if (!$this->config->isEnabled()) {
            return [];
        }
        $cached = $this->cache->load($this->optionCacheKey($attributeCode));
        if ($cached === false) {
            return [];
        }
        $options = $this->json->unserialize($cached);

        return $this->options[$attributeCode] = is_array($options) ? array_values($options) : [];
    }

    public function getOptionCounts(array $attributeCodes): array
    {
        return [];
    }

    /** @return array{imported: int, changed: int} */
    public function refresh(): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('The active Ergonode connection is disabled.'));
        }

        $previous = [];
        foreach ($this->getAttributes() as $attribute) {
            $previous[(string)$attribute['code']] = $attribute;
        }

        $attributes = $this->fetchAll();
        $changed = 0;
        foreach ($attributes as $code => $attribute) {
            if (($previous[$code] ?? null) !== $attribute) {
                ++$changed;
            }
        }
        foreach (array_diff_key($previous, $attributes) as $_) {
            ++$changed;
        }

        if (!$this->cache->save(
            $this->json->serialize(array_values($attributes)),
            $this->cacheKey(),
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        )) {
            throw new LocalizedException(__('Unable to store fetched Ergonode attribute metadata.'));
        }

        $this->attributes = array_values($attributes);

        return ['imported' => count($attributes), 'changed' => $changed];
    }

    /** @return array{imported: int, changed: int} */
    public function refreshOptions(string $attributeCode): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('The active Ergonode connection is disabled.'));
        }
        $previous = array_column($this->getOptions($attributeCode), null, 'code');
        $options = $this->fetchOptions($attributeCode);
        $changed = count(array_diff_key($previous, $options));
        foreach ($options as $code => $option) {
            if (($previous[$code] ?? null) !== $option) {
                ++$changed;
            }
        }
        if (!$this->cache->save(
            $this->json->serialize(array_values($options)),
            $this->optionCacheKey($attributeCode),
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        )) {
            throw new LocalizedException(__('Unable to store fetched Ergonode option metadata.'));
        }
        $this->options[$attributeCode] = array_values($options);

        return ['imported' => count($options), 'changed' => $changed];
    }

    /** @return array<string, array<string, mixed>> */
    private function fetchOptions(string $attributeCode): array
    {
        $languageCodes = $this->languagePreference();
        $guard = $this->guards->create('product attribute mapping options');
        $cursor = null;
        $options = [];
        do {
            $data = $this->client->query(self::OPTION_QUERY, [
                'code' => $attributeCode,
                'first' => self::PAGE_SIZE,
                'after' => $cursor,
            ]);
            $page = $data['attributeOptionList'] ?? null;
            if (!is_array($page) || !is_array($page['edges'] ?? null)
                || !is_array($page['pageInfo'] ?? null)
                || !is_bool($page['pageInfo']['hasNextPage'] ?? null)
                || !array_key_exists('endCursor', $page['pageInfo'])
                || ($page['pageInfo']['endCursor'] !== null && !is_string($page['pageInfo']['endCursor']))) {
                throw new LocalizedException(__('Ergonode returned an incomplete option list.'));
            }
            foreach ($page['edges'] as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;
                $code = is_array($node) ? trim((string)($node['code'] ?? '')) : '';
                if ($code === '' || isset($options[$code])) {
                    throw new LocalizedException(__('Ergonode returned an invalid or duplicate option code.'));
                }
                $options[$code] = [
                    'code' => $code,
                    'label' => $this->labelForLanguage($node['name'] ?? null, $languageCodes, $code),
                    'names' => $this->namesByLanguage($node['name'] ?? null),
                    'type' => 'option',
                    'scope' => 'unknown', 'active' => true,
                ];
            }
            $cursor = $guard->next($page['pageInfo']);
        } while ($cursor !== null);

        return $options;
    }

    /** @return array<string, array<string, mixed>> */
    private function fetchAll(): array
    {
        $languageCodes = $this->languagePreference();
        $guard = $this->guards->create('product attribute mapping definitions');
        $cursor = null;
        $attributes = [];
        do {
            $data = $this->client->query(self::ATTRIBUTE_QUERY, [
                'first' => self::PAGE_SIZE,
                'after' => $cursor,
            ]);
            $page = $data['attributeStream'] ?? null;
            if (!is_array($page) || !is_array($page['edges'] ?? null)
                || !is_array($page['pageInfo'] ?? null)
                || !is_bool($page['pageInfo']['hasNextPage'] ?? null)
                || !array_key_exists('endCursor', $page['pageInfo'])
                || ($page['pageInfo']['endCursor'] !== null && !is_string($page['pageInfo']['endCursor']))) {
                throw new LocalizedException(__('Ergonode returned an incomplete attribute list.'));
            }

            foreach ($page['edges'] as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;
                if (!is_array($node)) {
                    throw new LocalizedException(__('Ergonode returned an invalid attribute definition.'));
                }
                $attribute = $this->normalize($node, $languageCodes);
                if (isset($attributes[$attribute['code']])) {
                    throw new LocalizedException(__('Ergonode returned a duplicate attribute code.'));
                }
                $attributes[$attribute['code']] = $attribute;
            }

            $cursor = $guard->next($page['pageInfo']);
        } while ($cursor !== null);

        return $attributes;
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private function normalize(array $node, array $languageCodes): array
    {
        $code = trim((string)($node['code'] ?? ''));
        $type = $this->types->fromDefinitionTypeName((string)($node['__typename'] ?? ''));
        $scope = strtolower(trim((string)($node['scope'] ?? '')));
        if ($code === '' || $type === null || !in_array($scope, ['global', 'local'], true)) {
            throw new LocalizedException(__('Ergonode returned invalid attribute metadata.'));
        }

        return [
            'code' => $code,
            'label' => $this->labelForLanguage($node['name'] ?? null, $languageCodes, $code),
            'type' => $this->types->toConsumerType($type),
            'scope' => $scope,
            'active' => true,
        ];
    }

    private function labelForLanguage(mixed $names, array $languageCodes, string $fallback): string
    {
        if ($languageCodes === [] || !is_array($names)) {
            return $fallback;
        }

        foreach ($languageCodes as $languageCode) {
            foreach ($names as $name) {
                if (!is_array($name) || ($name['language'] ?? null) !== $languageCode) {
                    continue;
                }
                $value = trim((string)($name['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $fallback;
    }

    /** @return array<string, string> */
    private function namesByLanguage(mixed $names): array
    {
        $result = [];
        foreach (is_array($names) ? $names : [] as $name) {
            if (!is_array($name)) {
                continue;
            }
            $language = trim((string)($name['language'] ?? ''));
            $value = trim((string)($name['value'] ?? ''));
            if ($language !== '' && $value !== '') {
                $result[$language] = $value;
            }
        }

        return $result;
    }

    /** @return list<string> */
    private function languagePreference(): array
    {
        $adminLanguage = $this->languageMappingProvider->getAdminLanguageCode();
        if ($adminLanguage === null) {
            return [];
        }

        $storeMap = $this->languageMappingProvider->getLanguageStoreMap();
        ksort($storeMap, SORT_NUMERIC);
        $languages = [$adminLanguage];
        foreach ($storeMap as $languageCode) {
            if (!in_array($languageCode, $languages, true)) {
                $languages[] = $languageCode;
            }
        }

        return $languages;
    }

    private function cacheKey(): string
    {
        return 'ergonode_product_attribute_ui_' . hash('sha256', implode('|', [
            $this->config->getEnvironment(),
            $this->config->getMode(),
            $this->config->getGraphQlUrl(),
            $this->config->getApiKey(),
            $this->json->serialize($this->languagePreference()),
        ]));
    }

    private function optionCacheKey(string $attributeCode): string
    {
        return $this->cacheKey() . '_option_' . hash('sha256', $attributeCode);
    }
}
