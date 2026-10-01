<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Sync;

use Ergonode\ProductCategoryPublisher\Api\Data\ProductCategoryStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductCategoryPublisher\Model\Config\CategoryPublicationConfig;
use Ergonode\ProductCategoryPublisher\Model\Data\ProductCategoryChange;

class ProductCategoryPublicationPolicy
{
    public function __construct(private readonly CategoryPublicationConfig $config)
    {
    }

    public function getBlockingMessage(ProductStateInterface $state): ?string
    {
        if (!$this->config->shouldRemoveMissingCategories()
            || $state->isDeleted()
            || ($state instanceof ProductCategoryStateInterface
                && $state->isCategorySourceAuthoritative())
        ) {
            return null;
        }

        return (string)__(
            'Product "%1" categories cannot match Ergonode because one or more Magento categories are not '
            . 'mapped to categories present in an active Ergonode tree.',
            $state->getSku()
        );
    }

    /** @param string[] $remoteCategoryCodes */
    public function plan(ProductCategoryStateInterface $state, array $remoteCategoryCodes): ProductCategoryChange
    {
        $desiredCategoryCodes = $state->getCategoryCodes();
        $addedCategoryCodes = array_values(array_diff($desiredCategoryCodes, $remoteCategoryCodes));
        $removedCategoryCodes = $this->config->shouldRemoveMissingCategories()
            ? array_values(array_diff($remoteCategoryCodes, $desiredCategoryCodes))
            : [];
        sort($addedCategoryCodes);
        sort($removedCategoryCodes);

        return new ProductCategoryChange($addedCategoryCodes, $removedCategoryCodes);
    }
}
