<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Category\Model\Import\PaginationStateResolver;
use Ergonode\CategoryAttributeConsumer\Model\GraphQl\CategoryAttributeQueries;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryEntityLoader implements CategoryEntityLoaderInterface
{
    private const int PAGE_SIZE = 100;
    public function __construct(
        private readonly CategoryAttributeSourcePreparation $attributePreparation,
        private readonly Client $client,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly CategoryEntityNormalizer $normalizer,
        private readonly PaginationStateResolver $paginationStateResolver,
        private readonly CategoryQueries $queries
    ) {
    }

    /**
     * @return array{
     *     code: string,
     *     labels: array<string, string>,
     *     attributes: array<int, array<string, mixed>>,
     *     hash: string,
     *     raw: array<string, mixed>
     * }|null
     */
    public function load(string $code): ?array
    {
        return $this->loadMany([$code])[$code] ?? null;
    }

    public function loadMany(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $this->attributePreparation->ensurePrepared();
        $entities = [];
        foreach (array_chunk(array_values(array_unique($codes)), CategoryQueries::ENTITY_BATCH_SIZE) as $chunk) {
            $entities += $this->loadBatch($chunk);
        }

        return $entities;
    }

    /**
     * @param list<string> $codes
     * @return array<string, array{code: string, labels: array<string, string>,
     *     attributes: array<int, array<string, mixed>>, hash: string, raw: array<string, mixed>}|null>
     */
    private function loadBatch(array $codes): array
    {
        $pending = array_fill_keys($codes, null);
        $categories = [];
        $result = [];
        do {
            $requested = array_map('strval', array_keys($pending));
            $query = $this->queries->entityBatch(
                $requested,
                $this->languageMappingProvider->getLanguageCodes(),
                CategoryAttributeQueries::CATEGORY_FIELDS,
                $pending,
                self::PAGE_SIZE
            );
            $data = $this->client->query($query['document'], $query['variables']);
            foreach ($requested as $index => $code) {
                $category = $data['category_' . $index] ?? null;
                if ($category === null) {
                    $result[$code] = null;
                    unset($pending[$code], $categories[$code]);
                    continue;
                }
                if (!is_array($category) || ($category['code'] ?? null) !== $code) {
                    throw new LocalizedException(__('Ergonode returned an unexpected category for "%1".', $code));
                }
                $connection = (array)($category['attributeList'] ?? []);
                $pagination = $this->paginationStateResolver->resolve(
                    (array)($connection['pageInfo'] ?? []),
                    $pending[$code],
                    __('Ergonode returned invalid pagination for category attributes.')
                );
                $categories[$code] ??= [
                    'code' => $code, 'name' => $category['name'] ?? [], 'attributeList' => ['edges' => []],
                ];
                foreach ((array)($connection['edges'] ?? []) as $edge) {
                    $categories[$code]['attributeList']['edges'][] = $edge;
                }
                if ($pagination['has_more']) {
                    $pending[$code] = $pagination['cursor'];
                } else {
                    $result[$code] = $this->normalizer->normalize($categories[$code]);
                    unset($pending[$code], $categories[$code]);
                }
            }
        } while ($pending !== []);

        return $result;
    }
}
