<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryDetailsLoader
{
    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $client,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly CategoryQueries $queries
    ) {
    }

    /**
     * @param string[] $codes
     * @return array<string, array<string, mixed>>
     * @throws LocalizedException
     */
    public function load(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $categories = [];
        $languages = $this->languageMappingProvider->getLanguageCodes();
        foreach (array_chunk(array_values(array_unique($codes)), CategoryQueries::ENTITY_BATCH_SIZE) as $chunk) {
            $query = $this->queries->entityBatch($chunk, $languages);
            $data = $this->client->queryWriteScope($query['document'], $query['variables']);
            foreach ($chunk as $index => $code) {
                $category = $data['category_' . $index] ?? null;
                if (!is_array($category) || ($category['code'] ?? null) !== $code
                    || !is_array($category['name'] ?? null)
                ) {
                    throw new LocalizedException(__(
                        'Ergonode did not return complete details for category "%1". Refresh the tree before saving.',
                        $code
                    ));
                }
                $categories[$code] = $category;
            }
        }

        return $categories;
    }
}
