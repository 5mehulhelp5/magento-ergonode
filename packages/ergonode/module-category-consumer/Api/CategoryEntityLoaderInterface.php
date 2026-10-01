<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryEntityLoaderInterface
{
    /**
     * Load the current category, including data supplied by optional consumers.
     *
     * @param string $code
     * @return array{code: string, labels: array<string, string>, attributes: array<int, array<string, mixed>>,
     *     hash: string, raw: array<string, mixed>}|null
     */
    public function load(string $code): ?array;
    /**
     * Load each unique code once. Missing categories are represented by null.
     *
     * @param string[] $codes
     * @return array<string, array{code: string, labels: array<string, string>,
     *     attributes: array<int, array<string, mixed>>, hash: string, raw: array<string, mixed>}|null>
     */
    public function loadMany(array $codes): array;
}
