<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

interface AttributeAutoMapperInterface
{
    /**
     * Suggest mappings without persisting them.
     *
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{
     *     matches: array<int, array{left: array<string, mixed>, right: array<string, mixed>}>,
     *     conflicts: array<int, array{
     *         code: string,
     *         ergonode_type: string,
     *         magento_type: string,
     *         reason: string
     *     }>
     * }
     */
    public function suggest(array $mappings, array $visibility = []): array;

    /**
     * Persist all currently available automatic matches.
     *
     * @return array{
     *     matched: int,
     *     conflicts: int,
     *     created: int,
     *     inserted: int,
     *     updated: int,
     *     deleted: int,
     *     unchanged: int
     * }
     */
    public function synchronize(): array;
}
