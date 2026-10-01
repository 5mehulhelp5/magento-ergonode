<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Api\Data;

interface ProductCategoryStateInterface
{
    public const string COLLECTION_CATEGORIES = 'categories';

    /**
     * Return desired Ergonode category codes assigned to the product.
     *
     * @return string[]
     */
    public function getCategoryCodes(): array;

    /**
     * Check whether the Magento category assignment source was complete.
     *
     * @return bool
     */
    public function isCategorySourceAuthoritative(): bool;
}
