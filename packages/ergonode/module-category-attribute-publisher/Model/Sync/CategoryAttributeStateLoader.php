<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Sync;

use Ergonode\Attribute\Api\AttributeValueNormalizerInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeStateDto;
use Ergonode\CategoryAttributePublisher\Model\Data\CategoryAttributeValue;
use Ergonode\Core\Api\CursorPaginationGuardFactoryInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeStateLoader
{
    private const int BATCH_SIZE = 50;

    private const string CATEGORY_FIELD = <<<'GRAPHQL'
category(code: $code) {
    code
    attributeList(first: $first, after: $after) {
      pageInfo { hasNextPage endCursor }
      edges {
        node {
          __typename
          attribute { code }
          ... on DateAttributeValue {
            dateAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on FileAttributeValue {
            fileAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on GalleryAttributeValue {
            galleryAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on ImageAttributeValue {
            imageAttributeValueTranslations: translations(languages: $languages) { language value { path } }
          }
          ... on MultiSelectAttributeValue {
            multiSelectAttributeValueTranslations: translations(languages: $languages) { language value { code } }
          }
          ... on NumberAttributeValue {
            numericAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on PriceAttributeValue {
            priceAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on ProductRelationAttributeValue {
            productRelationAttributeValueTranslations: translations(languages: $languages) { language value { sku } }
          }
          ... on SelectAttributeValue {
            selectAttributeValueTranslations: translations(languages: $languages) { language value { code } }
          }
          ... on TextAttributeValue {
            textAttributeValueTranslations: translations(languages: $languages) { language value }
          }
          ... on TextareaAttributeValue {
            textareaAttributeValueTranslations: translations(languages: $languages) { language rawValue }
          }
          ... on UnitAttributeValue {
            unitAttributeValueTranslations: translations(languages: $languages) { language value }
          }
        }
      }
    }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $client,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly AttributeValueNormalizerInterface $valueNormalizer,
        private readonly WriteScopeCategoryAttributeCodeLoaderInterface $attributeRegistryLoader,
        private readonly CursorPaginationGuardFactoryInterface $paginationGuardFactory
    ) {
    }

    /** @param string[] $languages */
    public function load(string $code, array $languages = []): CategoryAttributeStateInterface
    {
        return $this->loadBatch([$code => $languages])[$code];
    }

    /**
     * Reads are local to this invocation; mutations and verification always get a fresh registry.
     * @param array<string, string[]> $languagesByCode
     * @return array<string, CategoryAttributeStateInterface>
     */
    public function loadBatch(array $languagesByCode): array
    {
        if ($languagesByCode === []) {
            return [];
        }
        $values = array_fill_keys(array_keys($languagesByCode), []);
        foreach (array_chunk($languagesByCode, self::BATCH_SIZE, true) as $scopes) {
            $cursors = array_fill_keys(array_keys($scopes), null);
            $guards = [];
            foreach (array_keys($scopes) as $code) {
                $guards[$code] = $this->paginationGuardFactory->create('category values ' . $code);
            }
            while ($cursors !== []) {
                $query = $this->batchQuery($scopes, $cursors);
                $data = $this->client->queryWriteScope($query['document'], $query['variables']);
                foreach (array_keys($cursors) as $index => $code) {
                    $category = $data['category_' . $index] ?? null;
                    if (!is_array($category) || ($category['code'] ?? null) !== (string)$code
                        || !is_array($category['attributeList'] ?? null)
                    ) {
                        throw new LocalizedException(__('Ergonode returned incomplete category "%1".', $code));
                    }
                    $connection = $category['attributeList'];
                    array_push($values[$code], ...$this->values($connection));
                    $after = $guards[$code]->next((array)($connection['pageInfo'] ?? []));
                    if ($after === null) {
                        unset($cursors[$code]);
                    } else {
                        $cursors[$code] = $after;
                    }
                }
            }
        }
        $allowedCodes = $this->attributeRegistryLoader->loadWriteScope();
        $states = [];
        foreach ($values as $code => $categoryValues) {
            $states[$code] = new CategoryAttributeStateDto($allowedCodes, $categoryValues);
        }
        return $states;
    }

    /**
     * @param array<string, string[]> $scopes
     * @param array<string, string|null> $cursors
     * @return array{document: string, variables: array<string, mixed>}
     */
    private function batchQuery(array $scopes, array $cursors): array
    {
        $definitions = ['$first: Int!'];
        $variables = ['first' => 100];
        $fields = [];
        foreach (array_keys($cursors) as $index => $code) {
            $definitions[] = '$code_' . $index . ': CategoryCode!';
            $definitions[] = '$languages_' . $index . ': [Language!]';
            $definitions[] = '$after_' . $index . ': String';
            $variables['code_' . $index] = (string)$code;
            $variables['languages_' . $index] = $scopes[$code] ?: null;
            $variables['after_' . $index] = $cursors[$code];
            $fields[] = 'category_' . $index . ': ' . strtr(self::CATEGORY_FIELD, [
                '$code' => '$code_' . $index,
                '$languages' => '$languages_' . $index,
                '$after' => '$after_' . $index,
            ]);
        }
        return [
            'document' => 'query PublisherCategoryAttributes(' . implode(', ', $definitions) . ') { '
                . implode(' ', $fields) . ' }',
            'variables' => $variables,
        ];
    }

    /** @param array<string, mixed> $connection @return CategoryAttributeValue[] */
    private function values(array $connection): array
    {
        $values = [];
        foreach ((array)($connection['edges'] ?? []) as $edge) {
            $node = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : [];
            $typeName = (string)($node['__typename'] ?? '');
            $type = $this->typeResolver->fromValueTypeName($typeName);
            $attribute = is_array($node['attribute'] ?? null) ? $node['attribute'] : [];
            $attributeCode = trim((string)($attribute['code'] ?? ''));
            if ($type === null
                || $attributeCode === ''
                || !isset(ErgonodeAttributeTypeInterface::TRANSLATION_KEYS[$typeName])
            ) {
                throw new LocalizedException(__(
                    'Ergonode returned an unsupported category attribute value type "%1".',
                    $typeName
                ));
            }
            $values[] = new CategoryAttributeValue(
                $attributeCode,
                $type,
                $this->valueNormalizer->valueTranslations(
                    $type,
                    $node[ErgonodeAttributeTypeInterface::TRANSLATION_KEYS[$typeName]] ?? []
                )
            );
        }
        return $values;
    }
}
