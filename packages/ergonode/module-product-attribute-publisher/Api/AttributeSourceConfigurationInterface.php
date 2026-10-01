<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeSourceConfigurationInterface
{
    /**
     * @return string
     * @throws LocalizedException
     */
    public function getPriceCurrency(): string;
}
