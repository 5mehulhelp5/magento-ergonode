<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\AttributeConsumer\Model\GraphQl\AttributeQueries;
use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Model\Import\AttributeNormalizer;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class OptionBatchImporter
{
    private const array PAGE_SIZES = [200, 100, 50, 25];

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
     *     processed: int,
     *     option_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function import(
        string $attributeCode,
        ?string $cursor = null,
        ?int $requestedPageSize = null,
        int $positionOffset = 0
    ): array {
        return $this->importWithScope(
            $attributeCode,
            $cursor,
            $requestedPageSize,
            $positionOffset,
            false
        );
    }

    /**
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     processed: int,
     *     option_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function importWriteScope(
        string $attributeCode,
        ?string $cursor = null,
        ?int $requestedPageSize = null,
        int $positionOffset = 0
    ): array {
        return $this->importWithScope(
            $attributeCode,
            $cursor,
            $requestedPageSize,
            $positionOffset,
            true
        );
    }

    /**
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     processed: int,
     *     option_codes: string[]
     * }
     * @throws LocalizedException
     */
    private function importWithScope(
        string $attributeCode,
        ?string $cursor,
        ?int $requestedPageSize,
        int $positionOffset,
        bool $writeScope
    ): array {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Missing Ergonode attribute code for option import.'));
        }
        $pageSize = $requestedPageSize ?: self::PAGE_SIZES[0];
        $data = $this->queryWithRetry($attributeCode, $cursor, $pageSize, $writeScope);
        [$list, $hasMore, $endCursor, $actualPageSize] = $this->pageValidator->validateOptionPage($data);
        $stats = [
            'imported' => 0,
            'changed' => 0,
            'unchanged' => 0,
        ];
        $options = [];

        foreach ($list['edges'] as $edge) {
            if (!is_array($edge) || !isset($edge['node']) || !is_array($edge['node'])) {
                continue;
            }

            $option = $this->normalizer->normalizeOption(
                $edge['node'],
                $positionOffset + count($options) + 1
            );
            if ($option['code'] === '') {
                continue;
            }

            $options[] = $option;
        }

        $results = $this->cacheWriter->saveOptions($attributeCode, $options);
        foreach ($options as $option) {
            $result = $results[$option['code']];
            $this->changeReport->add(
                'option',
                $attributeCode . '::' . (string)$option['code'],
                $result,
                $this->resolveReportMessage($result),
                ['attribute_code' => $attributeCode]
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
            'processed' => count($options),
            'option_codes' => array_values(array_column($options, 'code')),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function queryWithRetry(
        string $attributeCode,
        ?string $cursor,
        int $requestedPageSize,
        bool $writeScope
    ): array {
        return $this->pageQueryRetrier->query(
            self::PAGE_SIZES,
            $requestedPageSize,
            fn (int $pageSize): array => $writeScope
                ? $this->client->queryWriteScope(
                    AttributeQueries::ATTRIBUTE_OPTION_LIST,
                    [
                        'code' => $attributeCode,
                        'first' => $pageSize,
                        'after' => $cursor,
                        'languages' => $this->languageMappingProvider->getLanguageCodes(),
                    ]
                )
                : $this->client->query(
                    AttributeQueries::ATTRIBUTE_OPTION_LIST,
                    [
                        'code' => $attributeCode,
                        'first' => $pageSize,
                        'after' => $cursor,
                        'languages' => $this->languageMappingProvider->getLanguageCodes(),
                    ]
                )
        );
    }

    private function resolveReportMessage(string $result): string
    {
        return match ($result) {
            ChangeReport::ACTION_INSERTED => 'Inserted Ergonode option cache row.',
            ChangeReport::ACTION_UPDATED => 'Updated Ergonode option cache row.',
            default => 'Ergonode option cache row is unchanged.',
        };
    }
}
