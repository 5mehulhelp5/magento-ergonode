<?php

declare(strict_types=1);

namespace Ergonode\Language\Api;

interface StoreViewProviderInterface
{
    /**
     * @return array<int, array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     locale: string,
     *     website: string,
     *     group: string
     * }>
     */
    public function getStoreViews(): array;

    /**
     * @return array<int, array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     locale: string,
     *     website: string,
     *     group: string
     * }>
     */
    public function getStoreViewMap(): array;
}
