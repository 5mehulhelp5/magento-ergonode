<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Model\Data;

final readonly class ProductCategoryChange
{
    /** @param string[] $addedCategoryCodes @param string[] $removedCategoryCodes */
    public function __construct(
        private array $addedCategoryCodes,
        private array $removedCategoryCodes
    ) {
    }

    /** @return string[] */
    public function getAddedCategoryCodes(): array
    {
        return $this->addedCategoryCodes;
    }

    /** @return string[] */
    public function getRemovedCategoryCodes(): array
    {
        return $this->removedCategoryCodes;
    }
}
