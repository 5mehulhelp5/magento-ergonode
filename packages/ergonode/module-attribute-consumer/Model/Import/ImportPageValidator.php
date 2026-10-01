<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Magento\Framework\Exception\LocalizedException;

class ImportPageValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array{array{edges: array<int, mixed>, pageInfo: array<string, mixed>}, bool, string|null, int}
     * @throws LocalizedException
     */
    public function validateAttributePage(array $data): array
    {
        return $this->validate($data, 'attributeStream', 'attribute', 'stream');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{array{edges: array<int, mixed>, pageInfo: array<string, mixed>}, bool, string|null, int}
     * @throws LocalizedException
     */
    public function validateOptionPage(array $data): array
    {
        return $this->validate($data, 'attributeOptionList', 'option', 'list');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{array{edges: array<int, mixed>, pageInfo: array<string, mixed>}, bool, string|null, int}
     * @throws LocalizedException
     */
    private function validate(array $data, string $responseKey, string $entity, string $collection): array
    {
        if (!isset($data[$responseKey]) || !is_array($data[$responseKey])) {
            throw new LocalizedException(__('Ergonode %1 response is missing %2.', $entity, $responseKey));
        }

        $page = $data[$responseKey];
        if (!isset($page['edges']) || !is_array($page['edges'])) {
            throw new LocalizedException(__('Ergonode %1 response is missing %2 edges.', $entity, $collection));
        }
        if (!isset($page['pageInfo']) || !is_array($page['pageInfo'])) {
            throw new LocalizedException(__('Ergonode %1 response is missing pagination metadata.', $entity));
        }

        $codes = [];
        foreach ($page['edges'] as $edge) {
            $code = is_array($edge) && is_array($edge['node'] ?? null) ? ($edge['node']['code'] ?? null) : null;
            if (!is_string($code) || trim($code) === '' || ($collection === 'list' && isset($codes[$code]))) {
                throw new LocalizedException(__(
                    'Ergonode %1 response contains an invalid or duplicate code.',
                    $entity
                ));
            }
            $codes[$code] = true;
        }

        $pageInfo = $page['pageInfo'];
        if (!array_key_exists('hasNextPage', $pageInfo) || !is_bool($pageInfo['hasNextPage'])) {
            throw new LocalizedException(__('Ergonode %1 response has invalid pagination state.', $entity));
        }
        if (array_key_exists('endCursor', $pageInfo)
            && $pageInfo['endCursor'] !== null
            && !is_string($pageInfo['endCursor'])
        ) {
            throw new LocalizedException(__('Ergonode %1 response has an invalid pagination cursor.', $entity));
        }

        $hasMore = $pageInfo['hasNextPage'];
        $endCursor = isset($pageInfo['endCursor']) ? trim($pageInfo['endCursor']) : null;
        if ($hasMore && ($endCursor === null || $endCursor === '')) {
            throw new LocalizedException(__('Ergonode %1 response has no cursor for the next page.', $entity));
        }

        $actualPageSize = $data['_page_size'] ?? null;
        if (!is_int($actualPageSize) || $actualPageSize <= 0) {
            throw new LocalizedException(__('Ergonode %1 response has an invalid page size.', $entity));
        }

        return [$page, $hasMore, $endCursor !== '' ? $endCursor : null, $actualPageSize];
    }
}
