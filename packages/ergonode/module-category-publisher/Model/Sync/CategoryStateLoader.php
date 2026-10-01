<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Model\Data\CategoryStateDto;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Magento\Framework\Exception\LocalizedException;

class CategoryStateLoader
{
    private const int EXISTENCE_BATCH_SIZE = 50;

    public function __construct(private readonly GraphQlWriteScopeQueryClientInterface $client)
    {
    }

    /**
     * Each category retains its own language scope; an empty list reads all translations.
     * @param array<string, string[]> $languagesByCode
     * @return array<string, CategoryStateInterface|null>
     */
    public function loadBatch(array $languagesByCode): array
    {
        $states = [];
        foreach (array_chunk($languagesByCode, self::EXISTENCE_BATCH_SIZE, true) as $chunk) {
            $definitions = [];
            $fields = [];
            $variables = [];
            foreach (array_keys($chunk) as $index => $code) {
                $definitions[] = '$code_' . $index . ': CategoryCode!';
                $definitions[] = '$languages_' . $index . ': [Language!]';
                $fields[] = 'category_' . $index . ': category(code: $code_' . $index
                    . ') { code name(languages: $languages_' . $index . ') { language value } }';
                $variables['code_' . $index] = (string)$code;
                $variables['languages_' . $index] = $chunk[$code] ?: null;
            }
            $query = 'query PublisherCategories(' . implode(', ', $definitions) . ') { '
                . implode(' ', $fields) . ' }';
            $data = $this->client->queryWriteScope($query, $variables);
            foreach (array_keys($chunk) as $index => $code) {
                $alias = 'category_' . $index;
                if (!array_key_exists($alias, $data)) {
                    throw new LocalizedException(__('Ergonode did not return category "%1".', $code));
                }
                $category = $data[$alias];
                if ($category === null) {
                    $states[$code] = null;
                    continue;
                }
                if (!is_array($category) || ($category['code'] ?? null) !== (string)$code
                    || !is_array($category['name'] ?? null)
                ) {
                    throw new LocalizedException(__('Ergonode returned incomplete category "%1".', $code));
                }
                $states[$code] = new CategoryStateDto((string)$code, $this->translations($category['name']));
            }
        }
        return $states;
    }

    /**
     * @param string[] $codes
     * @return array<string, CategoryStateInterface|null>
     */
    public function loadExistenceBatch(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn (string $code): string => trim($code), $codes),
            static fn (string $code): bool => $code !== ''
        )));
        $states = array_fill_keys($codes, null);

        foreach (array_chunk($codes, self::EXISTENCE_BATCH_SIZE) as $chunk) {
            $definitions = [];
            $fields = [];
            $variables = [];
            foreach ($chunk as $index => $code) {
                $variable = 'code_' . $index;
                $alias = 'category_' . $index;
                $definitions[] = '  $' . $variable . ': CategoryCode!';
                $fields[] = '  ' . $alias . ': category(code: $' . $variable . ') { code }';
                $variables[$variable] = $code;
            }
            $query = "query PublisherCategoryExistence(\n"
                . implode("\n", $definitions)
                . "\n) {\n"
                . implode("\n", $fields)
                . "\n}";
            $data = $this->client->queryWriteScope($query, $variables);

            foreach ($chunk as $index => $code) {
                $alias = 'category_' . $index;
                if (!array_key_exists($alias, $data)) {
                    throw new MutationVerificationException(__('Ergonode did not return category "%1".', $code));
                }
                $category = $data[$alias];
                if ($category === null) {
                    continue;
                }
                if (!is_array($category) || !is_string($category['code'] ?? null)) {
                    throw new MutationVerificationException(__('Ergonode returned incomplete category "%1".', $code));
                }
                $remoteCode = trim((string)($category['code'] ?? ''));
                if ($remoteCode !== $code) {
                    throw new LocalizedException(__(
                        'Ergonode returned unexpected category code "%1" for "%2".',
                        $remoteCode,
                        $code
                    ));
                }
                $states[$code] = new CategoryStateDto($remoteCode);
            }
        }

        return $states;
    }

    /** @return array<string, string> */
    private function translations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }
        $result = [];
        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $language = trim((string)($translation['language'] ?? ''));
            $value = $translation['value'] ?? null;
            if ($language !== '' && is_string($value)) {
                $result[$language] = $value;
            }
        }
        ksort($result);

        return $result;
    }
}
