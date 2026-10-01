<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryAttributeRegistryRefresherInterface
{
    /**
     * @return array{imported: int}
     * @throws LocalizedException
     */
    public function refresh(): array;
}
