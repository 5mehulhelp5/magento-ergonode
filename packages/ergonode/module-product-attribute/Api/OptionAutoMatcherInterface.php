<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

use Magento\Framework\Exception\LocalizedException;

interface OptionAutoMatcherInterface
{
    /**
     * Suggest option mappings without persisting them.
     *
     * @param  int                              $attributeMappingId
     * @param  array<int|string, array<string, mixed>> $ergonodeOptions
     * @param  array<int, array<string, mixed>> $magentoOptions
     * @param  array<int, string>                $storeLanguages
     * @return array{matches: array<int, array{
     *     left: array<string, mixed>,
     *     right: array<string, mixed>
     * }>, conflicts: array<string, string>, unmatched: array<int, string>}
     * @throws LocalizedException
     */
    public function suggest(
        int $attributeMappingId,
        array $ergonodeOptions,
        array $magentoOptions,
        array $storeLanguages = []
    ): array;
}
