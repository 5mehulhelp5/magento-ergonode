<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryNameTargetProviderInterface
{
    /** @return string|null The Magento text attribute receiving the Ergonode category name. */
    public function getAttributeCode(): ?string;
}
