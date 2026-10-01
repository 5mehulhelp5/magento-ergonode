<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

interface LanguageStoreMappingRowsProviderInterface
{
    /**
     * @return list<array{
     *     mapping_id: int,
     *     store_id: int|null,
     *     language_code: string|null,
     *     sort_order: int,
     *     is_manual: int
     * }>
     */
    public function getRows(): array;
}
