<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryOptionAutoMatcherInterface
{
    /**
     * Suggest option mappings without persisting them.
     *
     * @param  int                              $attributeMappingId
     * @param  array<int, array<string, mixed>> $ergonodeOptions
     * @param  array<int, array<string, mixed>> $magentoOptions
     * @return array{matches: array<int, array{
     *     left: array<string, mixed>,
     *     right: array<string, mixed>
     * }>}
     * @throws LocalizedException
     */
    public function suggest(
        int $attributeMappingId,
        array $ergonodeOptions,
        array $magentoOptions
    ): array;
}
