<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

interface ProductPublicationSynchronizerInterface
{
    /**
     * Publish exactly the selected Magento products; never publish unselected dependencies.
     *
     * Missing attribute, category and template prerequisites are reported but never published here.
     *
     * @param string[] $skus
     * @param string $mode
     * @return array<string, ProductSynchronizationResultInterface> Results keyed by Magento SKU.
     * @throws LocalizedException
     */
    public function synchronize(
        array $skus,
        string $mode = ProductSynchronizerInterface::MODE_UPDATE
    ): array;
}
