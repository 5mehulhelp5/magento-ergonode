<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Api\PageQueryRetrierInterface;

class PageQueryRetrier implements PageQueryRetrierInterface
{
    public function query(array $pageSizes, int $requestedPageSize, callable $query): array
    {
        $pageSizes = array_map(
            static fn (mixed $size): int => (int)$size,
            array_values($pageSizes)
        );
        $sizes = array_values(array_filter(
            $pageSizes,
            static fn (int $size): bool => $size <= $requestedPageSize
        ));
        if ($sizes === []) {
            $sizes = [(int)$pageSizes[array_key_last($pageSizes)]];
        }

        foreach ($sizes as $index => $pageSize) {
            try {
                $data = $query($pageSize);
                $data['_page_size'] = $pageSize;

                return $data;
            } catch (LocalizedException $exception) {
                $isLastAttempt = $index === array_key_last($sizes);
                if ($isLastAttempt || !$this->canBenefitFromSmallerPage($exception)) {
                    throw $exception;
                }
            }
        }

        throw new LocalizedException(__('Unable to query an Ergonode GraphQL page.'));
    }

    private function canBenefitFromSmallerPage(LocalizedException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'complex');
    }
}
