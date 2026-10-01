<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class CategoryEntityLoader implements CategoryEntityLoaderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly Json $json,
        private readonly CategoryQueries $queries
    ) {
    }

    public function load(string $code): ?array
    {
        return $this->loadMany([$code])[$code] ?? null;
    }

    public function loadMany(array $codes): array
    {
        $result = [];
        foreach (array_chunk(array_values(array_unique($codes)), CategoryQueries::ENTITY_BATCH_SIZE) as $chunk) {
            $query = $this->queries->entityBatch($chunk, $this->languageMappingProvider->getLanguageCodes());
            $data = $this->client->query($query['document'], $query['variables']);
            foreach ($chunk as $index => $code) {
                $category = $data['category_' . $index] ?? null;
                if ($category !== null && (!is_array($category) || ($category['code'] ?? null) !== $code)) {
                    throw new LocalizedException(__('Ergonode returned an unexpected category for "%1".', $code));
                }
                $result[$code] = is_array($category) ? $this->normalize($category) : null;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $category
     * @return array{code: string, labels: array<string, string>, attributes: array<int, array<string, mixed>>,
     *     hash: string, raw: array<string, mixed>}
     */
    private function normalize(array $category): array
    {
        $labels = [];
        foreach ((array)($category['name'] ?? []) as $translation) {
            if (is_array($translation) && isset($translation['language'], $translation['value'])) {
                $labels[(string)$translation['language']] = (string)$translation['value'];
            }
        }
        ksort($labels);

        return [
            'code' => (string)($category['code'] ?? ''),
            'labels' => $labels,
            'attributes' => [],
            'hash' => hash('sha256', $this->json->serialize($labels)),
            'raw' => $category,
        ];
    }
}
