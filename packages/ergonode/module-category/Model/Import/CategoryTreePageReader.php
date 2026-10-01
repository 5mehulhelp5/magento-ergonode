<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryTreePageReader
{
    public function __construct(
        private readonly GraphQlQueryClientInterface $readClient,
        private readonly GraphQlWriteScopeQueryClientInterface $writeClient,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly PageQueryRetrierInterface $pageQueryRetrier,
        private readonly TreePageSizePolicy $pageSizePolicy
    ) {
    }

    /** @return array{data: array<string, mixed>, seconds: float} */
    public function read(string $treeCode, ?string $cursor, int $pageSize): array
    {
        return $this->query(
            $treeCode,
            $cursor,
            $pageSize,
            fn (string $document, array $variables): array => $this->readClient->query($document, $variables)
        );
    }

    /** @return array{data: array<string, mixed>, seconds: float} */
    public function readWriteScope(string $treeCode, ?string $cursor, int $pageSize): array
    {
        return $this->query(
            $treeCode,
            $cursor,
            $pageSize,
            fn (string $document, array $variables): array => $this->writeClient->queryWriteScope($document, $variables)
        );
    }

    /**
     * @param callable(string, array<string, mixed>): array<string, mixed> $query
     * @return array{data: array<string, mixed>, seconds: float}
     * @throws LocalizedException
     */
    private function query(string $treeCode, ?string $cursor, int $pageSize, callable $query): array
    {
        $seconds = 0.0;
        $languages = $this->languageMappingProvider->getLanguageCodes();
        $data = $this->pageQueryRetrier->query(
            $this->pageSizePolicy->retrySizes($pageSize),
            $pageSize,
            static function (int $requestedSize) use ($query, $treeCode, $cursor, $languages, &$seconds): array {
                $started = hrtime(true);
                $data = $query(CategoryQueries::CATEGORY_TREE, [
                    'code' => $treeCode,
                    'first' => $requestedSize,
                    'after' => $cursor,
                    'languages' => $languages,
                ]);
                $seconds = (hrtime(true) - $started) / 1_000_000_000;

                return $data;
            }
        );

        return ['data' => $data, 'seconds' => $seconds];
    }
}
