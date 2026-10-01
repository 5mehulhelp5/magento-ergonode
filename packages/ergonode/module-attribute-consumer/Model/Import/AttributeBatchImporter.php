<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Ergonode\AttributeConsumer\Api\AttributeBatchImporterInterface;
use Ergonode\AttributeConsumer\Model\GraphQl\AttributeQueries;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeBatchImporter implements AttributeBatchImporterInterface
{
    private const array PAGE_SIZES = [200, 100, 50, 25];
    private const array SKIPPED_ATTRIBUTE_TYPES = ['gallery'];

    public function __construct(
        private readonly Client $client,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly AttributeNormalizer $normalizer,
        private readonly AttributeCacheWriter $cacheWriter,
        private readonly ChangeReport $changeReport,
        private readonly ImportPageValidator $pageValidator,
        private readonly PageQueryRetrierInterface $pageQueryRetrier
    ) {
    }

    /**
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     attribute_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function import(?string $cursor = null, ?int $requestedPageSize = null): array
    {
        $pageSize = $requestedPageSize ?: self::PAGE_SIZES[0];
        $data = $this->queryWithRetry($cursor, $pageSize);
        [$stream, $hasMore, $endCursor, $actualPageSize] = $this->pageValidator->validateAttributePage($data);
        $stats = [
            'imported' => 0,
            'changed' => 0,
            'unchanged' => 0,
        ];
        $attributes = [];

        foreach ($stream['edges'] as $edge) {
            if (!is_array($edge) || !isset($edge['node']) || !is_array($edge['node'])) {
                continue;
            }

            $attribute = $this->normalizer->normalizeAttribute($edge['node']);
            if ($attribute['code'] === '') {
                continue;
            }

            if (in_array((string)$attribute['type'], self::SKIPPED_ATTRIBUTE_TYPES, true)) {
                $this->changeReport->add(
                    'attribute',
                    (string)$attribute['code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Skipped unsupported Ergonode attribute type.',
                    ['type' => (string)$attribute['type']]
                );
                continue;
            }

            $attributes[] = $attribute;
        }

        $results = $this->cacheWriter->saveAttributes($attributes);
        foreach ($attributes as $attribute) {
            $result = $results[$attribute['code']];
            $this->changeReport->add(
                'attribute',
                (string)$attribute['code'],
                $result,
                $this->resolveReportMessage($result, 'attribute'),
                [
                    'type' => (string)$attribute['type'],
                    'scope' => (string)$attribute['scope'],
                ]
            );
            if ($result === 'unchanged') {
                $stats['unchanged']++;
            } else {
                $stats['imported']++;
                $stats['changed']++;
            }
        }

        return [
            'has_more' => $hasMore,
            'cursor' => $endCursor,
            'page_size' => $actualPageSize,
            'imported' => $stats['imported'],
            'changed' => $stats['changed'],
            'unchanged' => $stats['unchanged'],
            'attribute_codes' => array_values(array_unique(array_map(
                static fn (array $attribute): string => (string)$attribute['code'],
                $attributes
            ))),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function queryWithRetry(?string $cursor, int $requestedPageSize): array
    {
        return $this->pageQueryRetrier->query(
            self::PAGE_SIZES,
            $requestedPageSize,
            fn (int $pageSize): array => $this->client->query(
                AttributeQueries::ATTRIBUTE_STREAM,
                [
                    'first' => $pageSize,
                    'after' => $cursor,
                    'languages' => $this->languageMappingProvider->getLanguageCodes(),
                ]
            )
        );
    }

    private function resolveReportMessage(string $result, string $entity): string
    {
        return match ($result) {
            ChangeReport::ACTION_INSERTED => sprintf('Inserted Ergonode %s cache row.', $entity),
            ChangeReport::ACTION_UPDATED => sprintf('Updated Ergonode %s cache row.', $entity),
            default => sprintf('Ergonode %s cache row is unchanged.', $entity),
        };
    }
}
