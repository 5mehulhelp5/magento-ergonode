<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryAutoMapperInterface
{
    /**
     * Suggest mappings from a saved source snapshot without changing Magento or persisted mappings.
     *
     * @param int $categoryTreeId
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draftMappings
     * @param array<int, array{source: string, identifier: string, active: bool}> $draftVisibility
     * @return array<string, mixed>
     */
    public function suggest(int $categoryTreeId, array $draftMappings = [], array $draftVisibility = []): array;
}
