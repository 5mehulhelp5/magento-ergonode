<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Api;

interface ImageRolesInterface
{
    /** @return array<string,string> Magento image attribute codes and labels. */
    public function getOptions(): array;
}
