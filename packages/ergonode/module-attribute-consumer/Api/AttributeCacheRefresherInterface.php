<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeCacheRefresherInterface
{
    /**
     * @return void
     * @throws LocalizedException
     */
    public function refreshAttributes(): void;

    /**
     * @param string $attributeCode
     * @return void
     * @throws LocalizedException
     */
    public function refreshOptions(string $attributeCode): void;
}
