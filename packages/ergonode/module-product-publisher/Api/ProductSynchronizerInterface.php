<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

interface ProductSynchronizerInterface
{
    public const string MODE_CREATE_ONLY = 'create_only';
    public const string MODE_UPDATE = 'update';
    public const string MODE_RECONCILE = 'reconcile';

    /**
     * Publishes Magento product commands with durable identity tracking. Mapped
     * identities require a complete remote existence read before create/update.
     * Complete comparison reads may omit unchanged templates and absent clears
     * for existing products. After confirmed creation and binding, a fresh read
     * may omit absent clears. Unavailable comparison reads preserve planned writes.
     *
     * @param ProductStateInterface $desiredState
     * @param string $mode
     * @return ProductSynchronizationResultInterface
     * @throws LocalizedException
     */
    public function synchronize(
        ProductStateInterface $desiredState,
        string $mode = self::MODE_UPDATE
    ): ProductSynchronizationResultInterface;
}
