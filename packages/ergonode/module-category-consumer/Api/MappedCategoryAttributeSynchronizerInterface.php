<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface MappedCategoryAttributeSynchronizerInterface
{
    /**
     * @param string $ergonodeCode
     * @param int $magentoCategoryId
     * @return array{snapshot: string, attributes: int}
     */
    public function synchronize(string $ergonodeCode, int $magentoCategoryId): array;
}
