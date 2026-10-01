<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryCreationDataProviderInterface
{
    /**
     * @param string $categoryCode
     * @return array{
     *     values: array{is_active: int, include_in_menu: int},
     *     entity: array{
     *         code: string,
     *         labels: array<string, string>,
     *         attributes: array<int, array<string, mixed>>,
     *         hash: string,
     *         raw: array<string, mixed>
     *     }|null
     * }
     */
    public function get(string $categoryCode): array;
}
