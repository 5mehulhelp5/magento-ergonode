<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryPositionWriterInterface
{
    /**
     * Move after the given sibling, or to the beginning when it is zero.
     *
     * @param int $categoryId
     * @param int $parentId
     * @param int $previousCategoryId
     * @return void
     */
    public function move(int $categoryId, int $parentId, int $previousCategoryId): void;
}
