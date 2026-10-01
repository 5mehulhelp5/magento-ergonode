<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Model\GraphQl\AttributeQueries;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeDefinitionLoader
{
    public function __construct(
        private readonly Client $client,
        private readonly AttributeNormalizer $normalizer,
        private readonly LanguageStoreMappingProviderInterface $languages,
        private readonly CursorPaginationGuardFactoryInterface $guards
    ) {
    }

    /** @return array{cursor: ?string, changed: bool} */
    public function changes(string $stream, ?string $cursor, bool $writeScope): array
    {
        $selection = $stream === 'attributeDeletedStream' ? 'node' : 'node { code }';
        $document = 'query AttributeDefinitionChanges($first: Int!, $after: String) { '
            . $stream . '(first: $first, after: $after) { pageInfo { hasNextPage endCursor } edges { '
            . $selection . ' } } }';
        $guard = $this->guards->create($stream);
        $after = $cursor;
        $changed = false;
        do {
            $page = $this->page($document, $stream, $after, $writeScope);
            foreach ($page['edges'] as $edge) {
                $code = $stream === 'attributeDeletedStream' ? ($edge['node'] ?? null)
                    : ($edge['node']['code'] ?? null);
                $this->requireCode($code);
                $changed = true;
            }
            $cursor = $page['pageInfo']['endCursor'] ?? $cursor;
            $after = $guard->next($page['pageInfo']);
        } while ($after !== null);

        return ['cursor' => $cursor, 'changed' => $changed];
    }

    /**
     * @return iterable<int, array{code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string}>
     */
    public function load(bool $writeScope): iterable
    {
        $guard = $this->guards->create('complete attribute snapshot');
        $after = null;
        $attributes = new DefinitionBuffer();
        do {
            $page = $this->page(AttributeQueries::ATTRIBUTE_STREAM, 'attributeStream', $after, $writeScope);
            foreach ($page['edges'] as $edge) {
                $node = $edge['node'] ?? null;
                if (!is_array($node)) {
                    throw new LocalizedException(__('Ergonode returned an invalid attribute definition.'));
                }
                $this->requireCode($node['code'] ?? null);
                $attribute = $this->normalizer->normalizeAttribute($node);
                $attributes->append($attribute);
            }
            $after = $guard->next($page['pageInfo']);
        } while ($after !== null);

        return $attributes;
    }

    /**
     * @return array{edges: array<int, array<string, mixed>>, pageInfo: array{hasNextPage: bool, endCursor: ?string}}
     */
    private function page(string $document, string $stream, ?string $after, bool $writeScope): array
    {
        $variables = ['first' => 200, 'after' => $after];
        if ($document === AttributeQueries::ATTRIBUTE_STREAM) {
            $variables['languages'] = $this->languages->getLanguageCodes();
        }
        $data = $writeScope ? $this->client->queryWriteScope($document, $variables)
            : $this->client->query($document, $variables);
        $page = $data[$stream] ?? null;
        if (!is_array($page) || !is_array($page['edges'] ?? null)
            || !is_array($page['pageInfo'] ?? null)
            || !is_bool($page['pageInfo']['hasNextPage'] ?? null)
            || !array_key_exists('endCursor', $page['pageInfo'])
            || ($page['pageInfo']['endCursor'] !== null && !is_string($page['pageInfo']['endCursor']))
            || ($page['edges'] !== [] && empty($page['pageInfo']['endCursor']))
            || ($page['pageInfo']['hasNextPage'] && $page['edges'] === [])
        ) {
            throw new LocalizedException(__('Ergonode returned an incomplete %1 response.', $stream));
        }

        return $page;
    }

    private function requireCode(mixed $code): void
    {
        if (!is_string($code) || trim($code) === '') {
            throw new LocalizedException(__('Ergonode returned an invalid attribute code.'));
        }
    }
}
