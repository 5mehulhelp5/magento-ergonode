<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\Attribute\Api\AttributeDataNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\AttributePublisher\Api\AttributeStateLoaderInterface;
use Ergonode\AttributePublisher\Api\AttributeBatchStateLoaderInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeOptionState;
use Ergonode\AttributePublisher\Model\Data\AttributeState;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeStateLoader implements AttributeStateLoaderInterface, AttributeBatchStateLoaderInterface
{
    private const int BATCH_SIZE = 50;

    private const string ATTRIBUTE_FIELD = <<<'GRAPHQL'
  attribute(code: $code) {
    __typename
    code
    scope
    name(languages: $languages) { language value }
    metadata { key value }
    ... on DateAttribute { format }
    ... on NumericAttribute { unique }
    ... on PriceAttribute { currency }
    ... on TextAttribute { unique }
    ... on TextareaAttribute { richEdit }
    ... on UnitAttribute { unit { name symbol } }
  }
GRAPHQL;

    private const string OPTIONS_FIELD = <<<'GRAPHQL'
  attributeOptionList(code: $code, first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges { node { code name(languages: $languages) { language value } } }
  }
GRAPHQL;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $client,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly AttributeDataNormalizerInterface $attributeDataNormalizer,
        private readonly CursorPaginationGuardFactoryInterface $guards
    ) {
    }

    /** @param string[] $languages */
    public function load(string $code, array $languages = []): ?AttributeStateInterface
    {
        return $this->loadBatch([$code], $languages)[$code];
    }

    /** @param string[] $codes @param string[] $languages @return array<string, AttributeStateInterface|null> */
    public function loadBatch(array $codes, array $languages = []): array
    {
        $states = [];
        foreach (array_chunk(array_values(array_unique($codes)), self::BATCH_SIZE) as $chunk) {
            $nodes = $this->loadDefinitions($chunk, $languages);
            $optionCodes = [];
            foreach ($nodes as $code => $node) {
                if ($node !== null && in_array($node['type'], ['select', 'multi_select'], true)) {
                    $optionCodes[] = (string)$code;
                }
            }
            $options = $this->loadOptions($optionCodes, $languages);
            foreach ($nodes as $code => $node) {
                $states[$code] = $node === null ? null : $this->state($node, $options[$code] ?? []);
            }
        }
        return $states;
    }

    /**
     * @param string[] $codes
     * @param string[] $languages
     * @return array<string, array<string, mixed>|null>
     */
    private function loadDefinitions(array $codes, array $languages): array
    {
        $query = $this->query($codes, $languages);
        $data = $this->client->queryWriteScope($query['document'], $query['variables']);
        $nodes = [];
        foreach ($codes as $index => $code) {
            $alias = 'attribute_' . $index;
            if (!array_key_exists($alias, $data) || ($data[$alias] !== null && !is_array($data[$alias]))) {
                throw new LocalizedException(__('Ergonode returned an incomplete attribute response.'));
            }
            $node = $data[$alias];
            if ($node !== null) {
                $runtimeType = trim((string)($node['__typename'] ?? ''));
                $type = $this->typeResolver->fromDefinitionTypeName($runtimeType);
                if ($type === null) {
                    throw new LocalizedException(__(
                        'Unsupported Ergonode attribute runtime type "%1".',
                        $runtimeType
                    ));
                }
                $node['type'] = $type;
                $node['code'] ??= $code;
            }
            $nodes[$code] = $node;
        }
        return $nodes;
    }

    /** @param array<string, mixed> $node @param AttributeOptionState[] $options */
    private function state(array $node, array $options): AttributeStateInterface
    {
        $type = (string)$node['type'];
        return new AttributeState(
            (string)$node['code'],
            $type,
            (string)($node['scope'] ?? ''),
            $this->attributeDataNormalizer->translations($node['name'] ?? []),
            $this->attributeDataNormalizer->parameters($type, $node),
            $this->metadata($node['metadata'] ?? []),
            $options
        );
    }

    /** @param string[] $codes @param string[] $languages @return array<string, AttributeOptionState[]> */
    private function loadOptions(array $codes, array $languages): array
    {
        $options = array_fill_keys($codes, []);
        $seen = array_fill_keys($codes, []);
        $cursors = array_fill_keys($codes, null);
        $guards = [];
        foreach ($codes as $code) {
            $guards[$code] = $this->guards->create('attribute options');
        }
        while ($cursors !== []) {
            $pending = array_map(strval(...), array_keys($cursors));
            $query = $this->query($pending, $languages, $cursors);
            $data = $this->client->queryWriteScope($query['document'], $query['variables']);
            foreach ($pending as $index => $code) {
                $connection = $this->optionConnection($data['options_' . $index] ?? null);
                foreach ($connection['edges'] as $edge) {
                    $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
                    $optionCode = trim((string)($node['code'] ?? ''));
                    if ($optionCode === '' || isset($seen[$code][$optionCode])) {
                        throw new LocalizedException(__(
                            'Ergonode returned an attribute option with an invalid or duplicate code.'
                        ));
                    }
                    $seen[$code][$optionCode] = true;
                    $options[$code][] = new AttributeOptionState(
                        $optionCode,
                        $this->attributeDataNormalizer->translations($node['name'] ?? [])
                    );
                }
                $after = $guards[$code]->next($connection['pageInfo']);
                if ($after === null || $after === '') {
                    unset($cursors[$code]);
                } else {
                    $cursors[$code] = $after;
                }
            }
        }
        return $options;
    }

    /** @return array{edges: array<int, mixed>, pageInfo: array<string, mixed>} */
    private function optionConnection(mixed $connection): array
    {
        $pageInfo = is_array($connection) ? ($connection['pageInfo'] ?? null) : null;
        if (!is_array($connection) || !is_array($connection['edges'] ?? null) || !is_array($pageInfo)
            || !is_bool($pageInfo['hasNextPage'] ?? null)
            || !array_key_exists('endCursor', $pageInfo)
            || ($pageInfo['endCursor'] !== null && !is_string($pageInfo['endCursor']))
            || ($pageInfo['hasNextPage'] && $connection['edges'] === [])
        ) {
            throw new LocalizedException(__('Ergonode returned invalid pagination for attribute options.'));
        }
        return ['edges' => $connection['edges'], 'pageInfo' => $pageInfo];
    }

    /**
     * @param string[] $codes
     * @param string[] $languages
     * @param array<string, string|null>|null $cursors
     * @return array{document: string, variables: array<string, mixed>}
     */
    private function query(array $codes, array $languages, ?array $cursors = null): array
    {
        $definitions = ['$languages: [Language!]'];
        $variables = ['languages' => $languages ?: null];
        $fields = [];
        if ($cursors !== null) {
            $definitions[] = '$first: Int!';
            $variables['first'] = 100;
        }
        foreach ($codes as $index => $code) {
            $definitions[] = '$code_' . $index . ': AttributeCode!';
            $variables['code_' . $index] = $code;
            if ($cursors !== null) {
                $definitions[] = '$after_' . $index . ': String';
                $variables['after_' . $index] = $cursors[$code];
            }
            $fields[] = ($cursors === null ? 'attribute_' : 'options_') . $index . ': '
                . strtr($cursors === null ? self::ATTRIBUTE_FIELD : self::OPTIONS_FIELD, [
                    '$code' => '$code_' . $index,
                    '$after' => '$after_' . $index,
                ]);
        }
        $name = $cursors === null ? 'PublisherAttributes' : 'PublisherAttributeOptionsBatch';
        return [
            'document' => 'query ' . $name . '(' . implode(', ', $definitions) . ') { '
                . implode(' ', $fields) . ' }',
            'variables' => $variables,
        ];
    }

    /** @return array<string, string> */
    private function metadata(mixed $items): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['key'])) {
                $result[(string)$item['key']] = (string)($item['value'] ?? '');
            }
        }
        return $result;
    }
}
