<?php

declare(strict_types=1);

namespace Ergonode\Product\Api\Data;

interface ProductIdentityInterface
{
    public const string MODE_SHARED = 'shared';
    public const string MODE_ASSIGNED = 'assigned';
    public const string MODE_MAPPED = 'mapped';

    /** @return int */
    public function getProductId(): int;

    /** @return string */
    public function getMagentoSku(): string;

    /** @return string */
    public function getErgonodeSku(): string;

    /** @return string */
    public function getIdentityMode(): string;
}
