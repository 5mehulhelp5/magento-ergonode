<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\Port;

interface AttributeDefinitionSnapshotInterface
{
    /** @return array{source: string, attribute: ?string, deleted: ?string}|null */
    public function getState(): ?array;

    /**
     * Replace the complete source snapshot and checkpoint atomically.
     *
     * @param iterable<int, array{
     *     code: string, type: string, scope: string, labels: array<string, string>,
     *     parameters: array<string, bool|string>, hash: string
     * }> $attributes
     * @param array{source: string, attribute: ?string, deleted: ?string} $state
     * @return string[] Removed definition codes; Magento data and mappings remain unchanged.
     */
    public function replace(iterable $attributes, array $state): array;
    /**
     * @return array{has_more: bool, cursor: ?string, page_size: int, imported: int, changed: int,
     *     unchanged: int, attribute_codes: list<string>}
     */
    public function page(?string $cursor, ?int $pageSize): array;
}
