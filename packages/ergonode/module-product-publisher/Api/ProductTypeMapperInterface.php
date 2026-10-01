<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Magento\Framework\Exception\LocalizedException;

interface ProductTypeMapperInterface
{
    /**
     * Maps a Magento product type ID to the normalized Ergonode product type.
     *
     * @param string $magentoTypeId
     * @return string
     * @throws LocalizedException
     */
    public function map(string $magentoTypeId): string;
}
