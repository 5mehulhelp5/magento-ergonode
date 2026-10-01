<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Data;

final readonly class MappingStateDto
{
    /**
     * @param string[] $codes
     * @param list<array{
     *     mapping_id: int, store_id: int|null, language_code: string|null, sort_order: int, is_manual: int
     * }> $rows
     * @param array<int, array{
     *     id: int, code: string, name: string, locale: string, website: string, group: string
     * }> $stores
     * @param array<string, bool> $languageVisibility
     * @param array<string, bool> $storeVisibility
     */
    public function __construct(
        public array $codes,
        public array $rows,
        public array $stores,
        public array $languageVisibility,
        public array $storeVisibility,
        public string $revision
    ) {
    }
}
